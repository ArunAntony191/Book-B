<?php
require_once 'db_helper.php';

class DeliveryManager {
    private $pdo;

    public function __construct() {
        $this->pdo = getDBConnection();
    }

    /**
     * Agent cancels a claimed job - results in a penalty
     */
    public function cancelJob($agentId, $transactionId) {
        $tx = $this->getTransaction($transactionId);

        if ($tx['delivery_agent_id'] != $agentId && (isset($tx['return_agent_id']) && $tx['return_agent_id'] != $agentId)) {
            throw new Exception("Unauthorized: You are not assigned to this delivery.");
        }

        if ($tx['status'] === 'delivered' || $tx['status'] === 'returned') {
            throw new Exception("Cannot cancel a completed delivery.");
        }

        // Handle return leg cancellation
        if (isset($tx['return_agent_id']) && $tx['return_agent_id'] == $agentId) {
            $sql = "UPDATE transactions SET 
                    return_agent_id = NULL, 
                    return_picked_up_at = NULL, 
                    return_agent_confirm_at = NULL,
                    status = 'delivered' 
                    WHERE id = ?";
            $this->pdo->prepare($sql)->execute([$transactionId]);
        } else {
            // Handle standard leg cancellation
            $sql = "UPDATE transactions SET 
                    delivery_agent_id = NULL, 
                    picked_up_at = NULL, 
                    agent_confirm_delivery_at = NULL,
                    status = 'approved' 
                    WHERE id = ?";
            $this->pdo->prepare($sql)->execute([$transactionId]);
        }

        // Apply Penalty: 5 credits for abandoning
        deductCredits($agentId, 5, 'penalty', "Abandoned delivery job #ORD-{$transactionId}", $transactionId);
        updateTrustScore($agentId, -5, 'job_abandoned');

        return true;
    }

    public function claimJob($agentId, $transactionId) {
        // Validate agent
        $stmt = $this->pdo->prepare("SELECT role, is_accepting_deliveries FROM users WHERE id = ?");
        $stmt->execute([$agentId]);
        $agent = $stmt->fetch();

        if (!$agent || $agent['role'] !== 'delivery_agent') {
            throw new Exception("Unauthorized: Not a delivery agent.");
        }
        
        // Start transaction for atomic check-and-claim
        $this->pdo->beginTransaction();
        
        try {
            // Check transaction state with row lock to prevent race conditions
            // Joins to get lender/borrower IDs and book title for notifications
            $stmt = $this->pdo->prepare("
                SELECT t.status, t.delivery_agent_id, t.return_delivery_method, t.return_agent_id,
                       t.lender_id, t.borrower_id, b.title
                FROM transactions t
                JOIN listings l ON t.listing_id = l.id
                JOIN books b ON l.book_id = b.id
                WHERE t.id = ? FOR UPDATE
            ");
            $stmt->execute([$transactionId]);
            $tx = $stmt->fetch();

            if (!$tx) {
                $this->pdo->rollBack();
                throw new Exception("Transaction not found.");
            }

            $jobAccepted = false;
            $msgPart = "";

            // Case 1: Standard pickup (Approved status, no agent)
            if ($tx['status'] === 'approved' && !$tx['delivery_agent_id']) {
                $sql = "UPDATE transactions SET delivery_agent_id = ? WHERE id = ?";
                $this->pdo->prepare($sql)->execute([$agentId, $transactionId]);
                $jobAccepted = true;
                $msgPart = "delivery assignment";
            } 
            
            // Case 2: Return leg (Returning status OR approved for return phase)
            elseif ($tx['return_delivery_method'] === 'delivery' && !$tx['return_agent_id']) {
                $sql = "UPDATE transactions SET return_agent_id = ?, status = 'returning' WHERE id = ?";
                $this->pdo->prepare($sql)->execute([$agentId, $transactionId]);
                $jobAccepted = true;
                $msgPart = "return mission";
            }

            if ($jobAccepted) {
                $this->pdo->commit();
                
                // Notify both parties
                $lenderMsg = "An agent has accepted the {$msgPart} for your book '{$tx['title']}'.";
                $borrowerMsg = "An agent has accepted the {$msgPart} for '{$tx['title']}'.";
                
                createNotification($tx['lender_id'], 'delivery_assigned', $lenderMsg, $transactionId);
                createNotification($tx['borrower_id'], 'delivery_assigned', $borrowerMsg, $transactionId);
                
                return true;
            }

            // Already claimed
            $this->pdo->rollBack();
            throw new Exception("Job already claimed or not ready for pickup.");
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Update delivery status with validations
     */
    public function updateStatus($agentId, $transactionId, $newStatus) {
        $tx = $this->getTransaction($transactionId);

        // Determine if this is the standard leg or return leg
        $isReturnLeg = (isset($tx['return_agent_id']) && $tx['return_agent_id'] == $agentId);
        $isStandardLeg = ($tx['delivery_agent_id'] == $agentId);

        if (!$isReturnLeg && !$isStandardLeg) {
            throw new Exception("Unauthorized: You are not assigned to this delivery leg.");
        }

        $currentStatus = $tx['status'];
        
        if ($newStatus === 'active') { // Standard Pickup
            // Flexible: Allow pickup if approved or assigned
            $this->pdo->prepare("UPDATE transactions SET picked_up_at = IFNULL(picked_up_at, NOW()) WHERE id = ?")->execute([$transactionId]);
            
            if ($currentStatus === 'approved' || $currentStatus === 'assigned') {
                $this->pdo->prepare("UPDATE transactions SET status = 'active' WHERE id = ?")->execute([$transactionId]);
            }
        } 
        elseif ($newStatus === 'returning_active') { // Return Pickup from Borrower
            $this->pdo->prepare("UPDATE transactions SET return_picked_up_at = IFNULL(return_picked_up_at, NOW()) WHERE id = ?")->execute([$transactionId]);
            
            if ($currentStatus === 'returning' || $currentStatus === 'delivered') {
                $this->pdo->prepare("UPDATE transactions SET status = 'returning' WHERE id = ?")->execute([$transactionId]);
            }

            // Notify parties
            createNotification($tx['lender_id'], 'delivery_update', "The agent has picked up '{$tx['title']}' from the borrower and is heading to you!", $transactionId);
            createNotification($tx['borrower_id'], 'delivery_update', "You have handed over '{$tx['title']}' to the agent.", $transactionId);
        }
        elseif ($newStatus === 'delivered') { // Agent marking as delivered (Standard Leg)
            if (!$tx['agent_confirm_delivery_at']) {
                $this->pdo->prepare("UPDATE transactions SET agent_confirm_delivery_at = NOW() WHERE id = ?")->execute([$transactionId]);
                
                // Award credits and money only once
                addCredits($tx['delivery_agent_id'], 10, 'earn', "Mission Completed: Deliver '{$tx['title']}'", $transactionId);
                addEarnings($tx['delivery_agent_id'], 50, "Mission Completed: Deliver '{$tx['title']}'");
                
                // If not picked up, mark as picked up now
                if (!$tx['picked_up_at']) {
                    $this->pdo->prepare("UPDATE transactions SET picked_up_at = NOW() WHERE id = ?")->execute([$transactionId]);
                }

                // Progress status if in forward phase
                if (in_array($currentStatus, ['approved', 'assigned', 'active'])) {
                    $this->pdo->prepare("UPDATE transactions SET status = 'delivered', delivered_at = NOW() WHERE id = ?")
                        ->execute([$transactionId]);
                }

                // COD + Doorstep Delivery: Cash collected by agent at delivery — mark payment as paid
                if ($tx['payment_method'] === 'cod' && $tx['delivery_method'] === 'delivery' && $tx['payment_status'] !== 'paid') {
                    $this->pdo->prepare("UPDATE transactions SET payment_status = 'paid' WHERE id = ?")->execute([$transactionId]);
                    $msg = "Your COD payment for '{$tx['title']}' has been collected by the delivery agent. Enjoy your book!";
                    createNotification($tx['borrower_id'], 'payment_confirmed', $msg, $transactionId);
                }
                
                // Notify Borrower
                $this->sendUpdateNotification($tx, 'delivered');
            }
        }
        elseif ($newStatus === 'return_delivered') { // Agent marking as delivered (Return Leg)
            if (!$tx['return_agent_confirm_at']) {
                $this->pdo->prepare("UPDATE transactions SET return_agent_confirm_at = NOW() WHERE id = ?")->execute([$transactionId]);

                if (!$tx['return_picked_up_at']) {
                    $this->pdo->prepare("UPDATE transactions SET return_picked_up_at = NOW() WHERE id = ?")->execute([$transactionId]);
                }

                // Keep status as 'returning' but update delivered timestamp
                $this->pdo->prepare("UPDATE transactions SET return_delivered_at = NOW() WHERE id = ?")
                    ->execute([$transactionId]);
                
                // Award credits and money only once
                addCredits($tx['return_agent_id'], 10, 'earn', "Mission Completed: Return '{$tx['title']}' to owner", $transactionId);
                addEarnings($tx['return_agent_id'], 50, "Mission Completed: Return '{$tx['title']}' to owner");

                // Notify parties
                $msg = "Return delivery complete! '{$tx['title']}' has been delivered back to the owner.";
                createNotification($tx['borrower_id'], 'return_complete', $msg, $transactionId);
                createNotification($tx['lender_id'], 'return_complete', $msg, $transactionId);
            }
        }
        else {
            throw new Exception("Invalid status update requested.");
        }

        return true;
    }

    public function confirmHandover($userId, $transactionId) {
        $tx = $this->getTransaction($transactionId);

        // Case 1: Standard Leg (Lender handing to agent OR direct pickup)
        if ($tx['lender_id'] == $userId) {
            $this->pdo->prepare("UPDATE transactions SET lender_confirm_at = IFNULL(lender_confirm_at, NOW()) WHERE id = ?")->execute([$transactionId]);
            // If not yet picked up by agent (officially), mark it picked up since owner says they handed it over
            if (!$tx['picked_up_at'] && in_array($tx['status'], ['approved', 'assigned'])) {
                 $this->pdo->prepare("UPDATE transactions SET status = 'active', picked_up_at = NOW() WHERE id = ?")->execute([$transactionId]);
            }

            // COD + Pickup: Cash is exchanged at handover — auto-mark payment as paid
            if ($tx['payment_method'] === 'cod' && $tx['delivery_method'] === 'pickup' && $tx['payment_status'] !== 'paid') {
                $this->pdo->prepare("UPDATE transactions SET payment_status = 'paid' WHERE id = ?")->execute([$transactionId]);
                // Notify buyer that payment is confirmed
                $msg = "Your COD payment for '{$tx['title']}' has been confirmed by the owner. Enjoy your book!";
                createNotification($tx['borrower_id'], 'payment_confirmed', $msg, $transactionId);
            }

            return true;
        }

        // Case 2: Return Leg (Borrower handing to agent)
        if ($tx['borrower_id'] == $userId) {
            $this->pdo->prepare("UPDATE transactions SET return_borrower_confirm_at = IFNULL(return_borrower_confirm_at, NOW()) WHERE id = ?")->execute([$transactionId]);
            if (!$tx['return_picked_up_at'] && ($tx['status'] === 'returning' || $tx['status'] === 'delivered')) {
                $this->pdo->prepare("UPDATE transactions SET status = 'returning', return_picked_up_at = NOW() WHERE id = ?")->execute([$transactionId]);
            }
            return true;
        }

        throw new Exception("Unauthorized or invalid transaction state.");
    }

    public function confirmReceipt($userId, $transactionId, $restock = false) {
        $tx = $this->getTransaction($transactionId);

        // Case 1: Standard Leg (Borrower receiving)
        if ($tx['borrower_id'] == $userId) {
            // Check if already confirmed to avoid double payment
            $alreadyConfirmed = !empty($tx['borrower_confirm_at']);

            // Update borrower confirmation
            $stmt = $this->pdo->prepare("UPDATE transactions SET borrower_confirm_at = IFNULL(borrower_confirm_at, NOW()) WHERE id = ?");
            $stmt->execute([$transactionId]);
            

            // Force status to delivered if not already there
            if (in_array($tx['status'], ['approved', 'assigned', 'active'])) {
                $this->pdo->prepare("UPDATE transactions SET status = 'delivered', delivered_at = IFNULL(delivered_at, NOW()) WHERE id = ?")->execute([$transactionId]);
            }

            // Stats update for both parties
            if (!$alreadyConfirmed) {
                $this->pdo->prepare("UPDATE users SET total_borrows = total_borrows + 1 WHERE id = ?")->execute([$userId]);
                $this->pdo->prepare("UPDATE users SET total_lends = total_lends + 1 WHERE id = ?")->execute([$tx['lender_id']]);
                
                // Set delivery time if not already set (important for direct pickup)
                if (empty($tx['delivered_at'])) {
                    $this->pdo->prepare("UPDATE transactions SET delivered_at = NOW() WHERE id = ?")->execute([$transactionId]);
                }
            }

            // Pay seller only if it's a borrow (token-based)
            // Purchases are handled via Cash (COD) or Online Payment
            if (!$alreadyConfirmed && $tx['transaction_type'] !== 'purchase') {
                 $credits = $tx['credit_cost'] ?? 10;
                 addCredits($tx['lender_id'], $credits, 'earn', "Book Handover: {$tx['title']}", $transactionId);
            }
            return true;
        }

        // Case 2: Return Leg (Lender receiving back)
        if ($tx['lender_id'] == $userId) {
            // Update lender confirmation
            $stmt = $this->pdo->prepare("UPDATE transactions SET return_lender_confirm_at = IFNULL(return_lender_confirm_at, NOW()) WHERE id = ?");
            $stmt->execute([$transactionId]);

            // Force status to returned
            if (in_array($tx['status'], ['approved', 'assigned', 'active', 'delivered', 'returning'])) {
                $this->pdo->prepare("UPDATE transactions SET status = 'returned', return_date = CURDATE(), return_delivered_at = IFNULL(return_delivered_at, NOW()) WHERE id = ?")
                     ->execute([$transactionId]);
            }

            // RESTOCK LOGIC
            if ($restock && empty($tx['is_restocked'])) {
                $this->pdo->prepare("UPDATE listings SET quantity = quantity + 1 WHERE id = ?")->execute([$tx['listing_id']]);
                $this->pdo->prepare("UPDATE transactions SET is_restocked = 1 WHERE id = ?")->execute([$transactionId]);
                createNotification($userId, 'book_restocked', "'{$tx['title']}' has been restocked!", $transactionId);
            }
            
            // PUNCTUALITY & LOYALTY REWARDS
            $onTime = true;
            $today = date('Y-m-d');
            if ($tx['due_date'] && $today > $tx['due_date']) {
                $onTime = false;
            }

            if ($onTime) {
                // Refund credit cost to borrower
                $refundAmount = (int)($tx['credit_cost'] ?? 10);
                
                // REGAIN LOGIC: Also refund delivery credits (10 for forward, if it was delivery)
                $deliveryCredits = ($tx['delivery_method'] === 'delivery') ? 10 : 0;
                $returnDeliveryCredits = (int)($tx['return_delivery_credits'] ?? 0);
                $totalRefund = $refundAmount + $deliveryCredits + $returnDeliveryCredits;

                addCredits($tx['borrower_id'], $totalRefund, 'refund', "Punctual Return Reward & Delivery Regain: '{$tx['title']}'", $transactionId);

                // Update punctuality stats
                $stmt = $this->pdo->prepare("UPDATE users SET punctuality_streak = punctuality_streak + 1, total_on_time_returns = total_on_time_returns + 1 WHERE id = ?");
                $stmt->execute([$tx['borrower_id']]);

                // Check for Loyalty Bonus (Every 5 streak)
                $stmt = $this->pdo->prepare("SELECT punctuality_streak FROM users WHERE id = ?");
                $stmt->execute([$tx['borrower_id']]);
                $streak = (int)$stmt->fetchColumn();

                if ($streak > 0 && $streak % 5 == 0) {
                    addCredits($tx['borrower_id'], 15, 'bonus', "Loyalty Bonus ({$streak} streak)", $transactionId);
                    createNotification($tx['borrower_id'], 'loyalty_bonus', "Congratulations! You earned a 15-token Loyalty Bonus for your 5-book punctuality streak! 🌟");
                }
            } else {
                // Late return - reset streak
                $this->pdo->prepare("UPDATE users SET punctuality_streak = 0 WHERE id = ?")->execute([$tx['borrower_id']]);
            }
                 
            return true;
        }

        throw new Exception("Unauthorized or invalid transaction state.");
    }

    private function getTransaction($id) {
        $stmt = $this->pdo->prepare("
            SELECT t.*, b.title 
            FROM transactions t
            JOIN listings l ON t.listing_id = l.id
            JOIN books b ON l.book_id = b.id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        if (!$data) throw new Exception("Transaction not found");
        return $data;
    }

    private function sendUpdateNotification($tx, $status) {
        $msg = "";
        $type = "delivery_update";
        
        if ($status === 'active') {
            $msg = "Your order for '{$tx['title']}' has been picked up & is on the way!";
        } elseif ($status === 'delivered') {
            $msg = "Delivered & Verified! '{$tx['title']}' has arrived.";
        }

        if ($msg) {
            createNotification($tx['borrower_id'], $type, $msg, $tx['id']);
        }
    }
}
?>
