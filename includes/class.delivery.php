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
        
        // Check transaction state
        $stmt = $this->pdo->prepare("SELECT status, delivery_agent_id, return_delivery_method, return_agent_id FROM transactions WHERE id = ?");
        $stmt->execute([$transactionId]);
        $tx = $stmt->fetch();

        if (!$tx) throw new Exception("Transaction not found.");

        // Case 1: Standard pickup (Approved status, no agent)
        if ($tx['status'] === 'approved' && !$tx['delivery_agent_id']) {
            $sql = "UPDATE transactions SET delivery_agent_id = ? WHERE id = ?";
            $this->pdo->prepare($sql)->execute([$agentId, $transactionId]);
            return true;
        } 
        
        // Case 2: Return leg (Returning status OR approved for return phase)
        if ($tx['return_delivery_method'] === 'delivery' && !$tx['return_agent_id']) {
            $sql = "UPDATE transactions SET return_agent_id = ?, status = 'returning' WHERE id = ?";
            $this->pdo->prepare($sql)->execute([$agentId, $transactionId]);
            return true;
        }

        throw new Exception("Job already claimed or not ready for pickup.");
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
        }
        elseif ($newStatus === 'delivered') { // Agent marking as delivered (Standard Leg)
            if (!$tx['agent_confirm_delivery_at']) {
                $this->pdo->prepare("UPDATE transactions SET agent_confirm_delivery_at = NOW() WHERE id = ?")->execute([$transactionId]);
                
                // Award credits only once
                addCredits($tx['delivery_agent_id'], 10, 'earn', "Mission Completed: Deliver '{$tx['title']}'", $transactionId);
                
                // If not picked up, mark as picked up now
                if (!$tx['picked_up_at']) {
                    $this->pdo->prepare("UPDATE transactions SET picked_up_at = NOW() WHERE id = ?")->execute([$transactionId]);
                }

                // Progress status if in forward phase
                if (in_array($currentStatus, ['approved', 'assigned', 'active'])) {
                    $this->pdo->prepare("UPDATE transactions SET status = 'delivered', delivered_at = NOW() WHERE id = ?")
                        ->execute([$transactionId]);
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
                
                // Award credits only once
                addCredits($tx['return_agent_id'], 10, 'earn', "Mission Completed: Return '{$tx['title']}' to owner", $transactionId);

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

        // Case 1: Standard Leg (Lender handing to agent)
        if ($tx['lender_id'] == $userId) {
            $this->pdo->prepare("UPDATE transactions SET lender_confirm_at = IFNULL(lender_confirm_at, NOW()) WHERE id = ?")->execute([$transactionId]);
            // If not yet picked up by agent (officially), mark it picked up since owner says they handed it over
            if (!$tx['picked_up_at'] && in_array($tx['status'], ['approved', 'assigned'])) {
                 $this->pdo->prepare("UPDATE transactions SET status = 'active', picked_up_at = NOW() WHERE id = ?")->execute([$transactionId]);
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

            // Pay seller only once
            if (!$alreadyConfirmed && $tx['transaction_type'] === 'purchase') {
                 $credits = $tx['credit_cost'] ?? 10;
                 addCredits($tx['lender_id'], $credits, 'earn', "Book Sold: {$tx['title']}", $transactionId);
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
            
            $this->pdo->prepare("UPDATE users SET total_lends = total_lends + 1 WHERE id = ?")->execute([$tx['lender_id']]);
                 
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
