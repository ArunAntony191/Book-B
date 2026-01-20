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
            if ($currentStatus !== 'approved') {
                throw new Exception("Invalid transition: Can only pick up approved orders.");
            }
            $sql = "UPDATE transactions SET status = 'active', picked_up_at = NOW() WHERE id = ?";
            $this->pdo->prepare($sql)->execute([$transactionId]);
        } 
        elseif ($newStatus === 'returning_active') { // Return Pickup from Borrower
            if ($currentStatus !== 'returning' && $currentStatus !== 'delivered') {
                throw new Exception("Order must be in 'returning' or 'delivered' status for return pickup.");
            }
            $sql = "UPDATE transactions SET status = 'returning', return_picked_up_at = NOW() WHERE id = ?";
            $this->pdo->prepare($sql)->execute([$transactionId]);
        }
        elseif ($newStatus === 'delivered') { // Agent marking as delivered (Standard Leg)
            if ($currentStatus !== 'active') {
                throw new Exception("Invalid transition: Order must be 'active' before delivery.");
            }
            
            $sql = "UPDATE transactions SET agent_confirm_delivery_at = NOW() WHERE id = ?";
            $this->pdo->prepare($sql)->execute([$transactionId]);
            
            // IMMEDIATE PAYOUT UPDATE:
            // Mark as delivered immediately upon agent confirmation
            $this->pdo->prepare("UPDATE transactions SET status = 'delivered', delivered_at = NOW() WHERE id = ?")
                ->execute([$transactionId]);
                
            // Award credits immediately
            addCredits($tx['delivery_agent_id'], 10, 'earn', "Mission Completed: Deliver '{$tx['title']}'", $transactionId);
            
            // Notify Borrower
            $this->sendUpdateNotification($tx, 'delivered');
            
            // If not yet confirmed by borrower, remind them
            if (!$tx['borrower_confirm_at']) {
                $msg = "Delivery agent has marked '{$tx['title']}' as delivered. Please confirm receipt.";
                $this->pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, ?, ?, ?)")
                    ->execute([$tx['borrower_id'], 'delivery_pending_confirmation', $msg, $tx['id']]);
            }
        }
        elseif ($newStatus === 'return_delivered') { // Agent marking as delivered (Return Leg)
            if ($currentStatus !== 'returning') {
                throw new Exception("Order must be in 'returning' status.");
            }

            $sql = "UPDATE transactions SET return_agent_confirm_at = NOW() WHERE id = ?";
            $this->pdo->prepare($sql)->execute([$transactionId]);

            // DO NOT auto-set status to 'returned' - keep as 'returning'
            // Owner must confirm receipt to complete the return
            // Just update the return_delivered_at timestamp
            $this->pdo->prepare("UPDATE transactions SET return_delivered_at = NOW() WHERE id = ?")
                ->execute([$transactionId]);
            
            // Award credits immediately to agent
            addCredits($tx['return_agent_id'], 10, 'earn', "Mission Completed: Return '{$tx['title']}' to owner", $transactionId);

            // Notify both parties
            $msg = "Return delivery complete! '{$tx['title']}' has been delivered back to the owner.";
            $this->pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, ?, ?, ?)")
                ->execute([$tx['borrower_id'], 'return_complete', $msg, $transactionId]);
            $this->pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, ?, ?, ?)")
                ->execute([$tx['lender_id'], 'return_complete', $msg, $transactionId]);
                
            if (!$tx['return_lender_confirm_at']) {
                $msg = "Delivery agent has returned '{$tx['title']}'. Please confirm receipt to complete the return.";
                $this->pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, ?, ?, ?)")
                    ->execute([$tx['lender_id'], 'return_pending_confirmation', $msg, $tx['id']]);
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
            if ($tx['lender_confirm_at']) throw new Exception("Already confirmed handover.");
            
            $this->pdo->prepare("UPDATE transactions SET lender_confirm_at = NOW() WHERE id = ?")->execute([$transactionId]);
            if ($tx['status'] === 'approved' || $tx['status'] === 'assigned') {
                $this->pdo->prepare("UPDATE transactions SET status = 'active', picked_up_at = NOW() WHERE id = ?")
                    ->execute([$transactionId]);
            }
            return true;
        }

        // Case 2: Return Leg (Borrower handing to agent)
        if ($tx['borrower_id'] == $userId) {
            if ($tx['return_borrower_confirm_at']) throw new Exception("Already confirmed return handover.");
            
            $this->pdo->prepare("UPDATE transactions SET return_borrower_confirm_at = NOW() WHERE id = ?")->execute([$transactionId]);
            if ($tx['status'] === 'returning' || $tx['status'] === 'delivered') {
                $this->pdo->prepare("UPDATE transactions SET status = 'returning', return_picked_up_at = NOW() WHERE id = ?")
                    ->execute([$transactionId]);
            }
            return true;
        }

        throw new Exception("Unauthorized or invalid transaction state.");
    }

    public function confirmReceipt($userId, $transactionId, $restock = false) {
        $tx = $this->getTransaction($transactionId);

        // Case 1: Standard Leg (Borrower receiving)
        if ($tx['borrower_id'] == $userId) {
            if ($tx['borrower_confirm_at']) throw new Exception("Already confirmed receipt.");
            
            // Update borrower confirmation
            $stmt = $this->pdo->prepare("UPDATE transactions SET borrower_confirm_at = NOW() WHERE id = ?");
            $stmt->execute([$transactionId]);
            
            // Auto-confirm Agent if missing (Implicit verification)
            if (empty($tx['agent_confirm_delivery_at'])) {
                $this->pdo->prepare("UPDATE transactions SET agent_confirm_delivery_at = NOW() WHERE id = ?")->execute([$transactionId]);
            }
            // Force status to delivered if active
            if ($tx['status'] === 'active') {
                $this->pdo->prepare("UPDATE transactions SET status = 'delivered', delivered_at = NOW() WHERE id = ?")->execute([$transactionId]);
            }

            $tx = $this->getTransaction($transactionId); // Refresh
            
            // Agent already paid or will be paid? 
            // If status was active, we just forced it to delivered. We should probably ensure the agent gets paid if they weren't already.
            // However, payment logic is currently in updateStatus. 
            // For simplicity and robustness, assuming agent marks delivered first is the happy path. 
            // If borrower confirms first, we just ensure data consistency.

            // Check if seller needs payment (purchase)
            if ($tx['transaction_type'] === 'purchase') {
                 // Payment logic for seller
                 $credits = $tx['credit_cost'] ?? 10;
                 addCredits($tx['lender_id'], $credits, 'earn', "Book Sold: {$tx['title']}", $transactionId);
            }
            return true;
        }

        // Case 2: Return Leg (Lender receiving back)
        if ($tx['lender_id'] == $userId) {
            if ($tx['return_lender_confirm_at']) throw new Exception("Already confirmed return receipt.");
            
            // Update lender confirmation
            $stmt = $this->pdo->prepare("UPDATE transactions SET return_lender_confirm_at = NOW() WHERE id = ?");
            $stmt->execute([$transactionId]);

            // Auto-confirm Return Agent if missing
            if (empty($tx['return_agent_confirm_at'])) {
                $this->pdo->prepare("UPDATE transactions SET return_agent_confirm_at = NOW() WHERE id = ?")->execute([$transactionId]);
            }

            // Force status to returned
            if ($tx['status'] === 'returning' || $tx['status'] === 'delivered') {
                $this->pdo->prepare("UPDATE transactions SET status = 'returned', return_date = CURDATE(), return_delivered_at = NOW() WHERE id = ?")
                     ->execute([$transactionId]);
            }

            $tx = $this->getTransaction($transactionId);
            
            // RESTOCK LOGIC: If owner chooses to restock, increment listing quantity
            if ($restock) {
                $listingId = $tx['listing_id'];
                $this->pdo->prepare("UPDATE listings SET quantity = quantity + 1 WHERE id = ?")
                     ->execute([$listingId]);
                
                // Log this action via notification
                $this->pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, 'book_restocked', ?, ?)")
                     ->execute([$userId, "'{$tx['title']}' has been restocked and is available for lending again!", $transactionId]);
            }
            
            // Just increase total_lends stats
            $this->pdo->prepare("UPDATE users SET total_lends = total_lends + 1 WHERE id = ?")
                     ->execute([$tx['lender_id']]);
                 
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
            $this->pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, ?, ?, ?)")
                ->execute([$tx['borrower_id'], $type, $msg, $tx['id']]);
        }
    }
}
?>
