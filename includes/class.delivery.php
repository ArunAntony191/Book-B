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
            
            // Re-fetch to check borrower confirm
            $tx = $this->getTransaction($transactionId);
            if ($tx['borrower_confirm_at']) {
                $this->pdo->prepare("UPDATE transactions SET status = 'delivered', delivered_at = NOW() WHERE id = ?")
                    ->execute([$transactionId]);
                addCredits($tx['delivery_agent_id'], 10, 'earn', "Mission Completed: Deliver '{$tx['title']}'", $transactionId);
                $this->sendUpdateNotification($tx, 'delivered');
            } else {
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

            // Re-fetch
            $tx = $this->getTransaction($transactionId);
            if ($tx['return_lender_confirm_at']) {
                $this->pdo->prepare("UPDATE transactions SET status = 'returned', return_delivered_at = NOW() WHERE id = ?")
                    ->execute([$transactionId]);
                
                addCredits($tx['return_agent_id'], 10, 'earn', "Mission Completed: Return '{$tx['title']}' to owner", $transactionId);
                
                $msg = "Return complete! '{$tx['title']}' has been delivered back to the owner.";
                $this->pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, ?, ?, ?)")
                    ->execute([$tx['borrower_id'], 'return_complete', $msg, $transactionId]);
                $this->pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, ?, ?, ?)")
                    ->execute([$tx['lender_id'], 'return_complete', $msg, $transactionId]);
            } else {
                $msg = "Delivery agent has returned '{$tx['title']}'. Owner, please confirm receipt.";
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

    public function confirmReceipt($userId, $transactionId) {
        $tx = $this->getTransaction($transactionId);

        // Case 1: Standard Leg (Borrower receiving)
        if ($tx['borrower_id'] == $userId) {
            if ($tx['borrower_confirm_at']) throw new Exception("Already confirmed receipt.");
            
            $stmt = $this->pdo->prepare("UPDATE transactions SET borrower_confirm_at = NOW() WHERE id = ?");
            $stmt->execute([$transactionId]);

            $tx = $this->getTransaction($transactionId);
            if ($tx['agent_confirm_delivery_at']) {
                 $this->pdo->prepare("UPDATE transactions SET status = 'delivered', delivered_at = NOW() WHERE id = ?")
                     ->execute([$transactionId]);
                 addCredits($tx['delivery_agent_id'], 10, 'earn', "Mission Completed: Deliver '{$tx['title']}'", $transactionId);
                 
                 if ($tx['transaction_type'] === 'purchase') {
                     $credits = $tx['credit_cost'] ?? 10;
                     addCredits($tx['lender_id'], $credits, 'earn', "Book Sold: {$tx['title']}", $transactionId);
                 }
            }
            return true;
        }

        // Case 2: Return Leg (Lender receiving back)
        if ($tx['lender_id'] == $userId) {
            if ($tx['return_lender_confirm_at']) throw new Exception("Already confirmed return receipt.");
            
            $stmt = $this->pdo->prepare("UPDATE transactions SET return_lender_confirm_at = NOW() WHERE id = ?");
            $stmt->execute([$transactionId]);

            $tx = $this->getTransaction($transactionId);
            if ($tx['return_agent_confirm_at']) {
                 $this->pdo->prepare("UPDATE transactions SET status = 'returned', return_delivered_at = NOW() WHERE id = ?")
                     ->execute([$transactionId]);
                 addCredits($tx['return_agent_id'], 10, 'earn', "Mission Completed: Return '{$tx['title']}'", $transactionId);
                 
                 // Also increase lender's lend count
                 $this->pdo->prepare("UPDATE users SET total_lends = total_lends + 1 WHERE id = ?")
                     ->execute([$tx['lender_id']]);
                 
                 return true;
            }
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
