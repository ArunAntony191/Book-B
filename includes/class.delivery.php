<?php
require_once 'db_helper.php';

class DeliveryManager {
    private $pdo;

    public function __construct() {
        $this->pdo = getDBConnection();
    }

    /**
     * Assign a job to an agent
     */
    /**
     * Agent cancels a claimed job - results in a penalty
     */
    public function cancelJob($agentId, $transactionId) {
        $tx = $this->getTransaction($transactionId);

        if ($tx['delivery_agent_id'] != $agentId) {
            throw new Exception("Unauthorized: You are not assigned to this delivery.");
        }

        if ($tx['status'] === 'delivered') {
            throw new Exception("Cannot cancel a completed delivery.");
        }

        // Reset the transaction so another agent can claim it
        $sql = "UPDATE transactions SET 
                delivery_agent_id = NULL, 
                picked_up_at = NULL, 
                agent_confirm_delivery_at = NULL,
                status = 'approved' 
                WHERE id = ?";
        $this->pdo->prepare($sql)->execute([$transactionId]);

        // Apply Penalty: 5 credits for abandoning
        deductCredits($agentId, 5, 'penalty', "Abandoned delivery job #ORD-{$transactionId}", $transactionId);
        updateTrustScore($agentId, -5, 'job_abandoned');

        // Notify Borrower & Lender
        $msg = "Delivery agent has cancelled the pickup for '{$tx['title']}'. A new agent will be assigned soon.";
        $this->pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, ?, ?, ?)")
            ->execute([$tx['borrower_id'], 'delivery_cancelled', $msg, $transactionId]);
        
        $this->pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, ?, ?, ?)")
            ->execute([$tx['lender_id'], 'delivery_cancelled', $msg, $transactionId]);

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
        
        // Removed check for is_accepting_deliveries to allow claiming even if technically 'off duty' if they want
        // ensuring maximum flexibility as requested

        // Check transaction state
        $stmt = $this->pdo->prepare("SELECT status, delivery_agent_id FROM transactions WHERE id = ?");
        $stmt->execute([$transactionId]);
        $tx = $stmt->fetch();

        if (!$tx) throw new Exception("Transaction not found.");
        if ($tx['delivery_agent_id']) throw new Exception("Job already claimed by another agent.");
        if ($tx['status'] !== 'approved') throw new Exception("Job is not ready for pickup.");

        // Claim
        $sql = "UPDATE transactions SET delivery_agent_id = ? WHERE id = ?";
        $this->pdo->prepare($sql)->execute([$agentId, $transactionId]);

        return true;
    }

    /**
     * Update delivery status with validations
     * Modified to support 2-party confirmation for delivery
     */
    public function updateStatus($agentId, $transactionId, $newStatus) {
        $tx = $this->getTransaction($transactionId);

        // Validation: Ownership
        if ($tx['delivery_agent_id'] != $agentId) {
            // Allow admin override? For now, strict.
            throw new Exception("Unauthorized: You are not assigned to this delivery.");
        }

        // Validation: Logic flow
        $currentStatus = $tx['status'];
        
        if ($newStatus === 'active') { // Picking up
            if ($currentStatus !== 'approved') {
                throw new Exception("Invalid transition: Can only pick up 'approved' orders.");
            }
            $sql = "UPDATE transactions SET status = 'active', picked_up_at = NOW() WHERE id = ?";
            $this->pdo->prepare($sql)->execute([$transactionId]);
        } 
        elseif ($newStatus === 'delivered') { // Agent marking as delivered
            if ($currentStatus !== 'active') {
                throw new Exception("Invalid transition: Order must be 'active' (in transit) before delivery.");
            }
            
            // Agent confirms delivery - but don't change status yet
            // Status only changes when BOTH agent AND borrower confirm
            $sql = "UPDATE transactions SET agent_confirm_delivery_at = NOW() WHERE id = ?";
            $this->pdo->prepare($sql)->execute([$transactionId]);
            
            // Check if borrower already confirmed
            if ($tx['borrower_confirm_at']) {
                // Both have confirmed! Now mark as delivered
                $this->pdo->prepare("UPDATE transactions SET status = 'delivered', delivered_at = NOW() WHERE id = ?")
                    ->execute([$transactionId]);
                
                // Reward Agent
                addCredits($tx['delivery_agent_id'], 10, 'earn', "Mission Completed: Deliver '{$tx['title']}'", $transactionId);

                // Reward Lender if it's a purchase
                if ($tx['transaction_type'] === 'purchase') {
                    $lenderCredits = $tx['credit_cost'] ?? 10;
                    addCredits($tx['lender_id'], $lenderCredits, 'earn', "Book Sold & Delivered: {$tx['title']}", $transactionId);
                }

                // Send final delivery notifications
                $this->sendUpdateNotification($tx, 'delivered');
            } else {
                // Only agent confirmed, send notification that we're waiting for borrower
                $msg = "Delivery agent has marked '{$tx['title']}' as delivered. Please confirm receipt.";
                $this->pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, ?, ?, ?)")
                    ->execute([$tx['borrower_id'], 'delivery_pending_confirmation', $msg, $tx['id']]);
            }
        }
        else {
            throw new Exception("Invalid status update requested.");
        }

        return true;
    }

    /**
     * Bulk update status (e.g. for admin or super-agent features)
     * Requested feature: "option to update delivery status for all user"
     */
    public function bulkUpdateStatus($agentId, $transactionIds, $newStatus) {
        $successCount = 0;
        $errors = [];

        foreach ($transactionIds as $tid) {
            try {
                if ($this->updateStatus($agentId, $tid, $newStatus)) {
                    $successCount++;
                }
            } catch (Exception $e) {
                $errors[] = "ID $tid: " . $e->getMessage();
            }
        }

        return ['success_count' => $successCount, 'errors' => $errors];
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
        } elseif ($status === 'agent_confirmed') {
            $msg = "Agent marked '{$tx['title']}' as delivered. Please confirm receipt to complete.";
        }

        if ($msg) {
            // Notify Borrower
            $this->pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, ?, ?, ?)")
                ->execute([$tx['borrower_id'], $type, $msg, $tx['id']]);
                
            // Notify Lender (optional, but good for trust)
            if ($status === 'delivered') {
                $lmsg = "Good news! '{$tx['title']}' was successfully delivered.";
                $this->pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, ?, ?, ?)")
                    ->execute([$tx['lender_id'], $type, $lmsg, $tx['id']]);
            }
        }
    }

    public function confirmHandover($userId, $transactionId) {
        $tx = $this->getTransaction($transactionId);
        
        if ($tx['lender_id'] != $userId) {
            throw new Exception("Unauthorized: You are not the lender of this transaction.");
        }
        
        if ($tx['lender_confirm_at']) {
            throw new Exception("You have already confirmed the handover.");
        }

        // Ideally, status should be 'active' (Agent picked up) or 'approved' (Agent arriving)
        // Let's allow it if agent is assigned.
        if (!$tx['delivery_agent_id']) {
            throw new Exception("No agent assigned yet.");
        }

        $stmt = $this->pdo->prepare("UPDATE transactions SET lender_confirm_at = NOW() WHERE id = ?");
        $stmt->execute([$transactionId]);

        // Auto-update status to 'active' (In Transit) if not already
        if ($tx['status'] === 'approved') {
            $this->pdo->prepare("UPDATE transactions SET status = 'active', picked_up_at = NOW() WHERE id = ?")
                ->execute([$transactionId]);
        }

        // Trust score bonus for Agent?
        // updateTrustScore($tx['delivery_agent_id'], 1, 'verified_handover');

        return true;
    }

    public function confirmReceipt($userId, $transactionId) {
        $tx = $this->getTransaction($transactionId);

        if ($tx['borrower_id'] != $userId) {
            throw new Exception("Unauthorized: You are not the borrower.");
        }
        if ($tx['borrower_confirm_at']) {
            throw new Exception("You have already confirmed receipt.");
        }
        
        // Status should be 'delivered' (Agent says so) or 'active' (if they meet)
        // If agent marked 'delivered', this verifies it.
        
        $stmt = $this->pdo->prepare("UPDATE transactions SET borrower_confirm_at = NOW() WHERE id = ?");
        $stmt->execute([$transactionId]);

        // Refresh transaction data to check agent confirmation
        $tx = $this->getTransaction($transactionId);

        // Status only updates to 'delivered' if agent has also confirmed
        if ($tx['agent_confirm_delivery_at']) {
             $this->pdo->prepare("UPDATE transactions SET status = 'delivered', delivered_at = NOW() WHERE id = ?")
                 ->execute([$transactionId]);
             
             // Reward Agent
             addCredits($tx['delivery_agent_id'], 10, 'earn', "Mission Completed: Deliver '{$tx['title']}'", $transactionId);

             // Reward Lender if it's a purchase
             if ($tx['transaction_type'] === 'purchase') {
                 $lenderCredits = $tx['credit_cost'] ?? 10;
                 addCredits($tx['lender_id'], $lenderCredits, 'earn', "Book Sold & Delivered: {$tx['title']}", $transactionId);
             }

             // Also notify Lender that it's done
             $msg = "Delivery verified! Borrower confirmed receipt of '{$tx['title']}'.";
             $this->pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, ?, ?, ?)")
                 ->execute([$tx['lender_id'], 'receipt_confirmed', $msg, $transactionId]);
        } else {
            // Notify Lender that borrower confirmed (but agent hasn't yet)
            $msg = "Borrower confirmed receipt of '{$tx['title']}'. Waiting for agent confirmation.";
            $this->pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, ?, ?, ?)")
                ->execute([$tx['lender_id'], 'borrower_confirmed', $msg, $transactionId]);
        }

        return true;
    }
}
?>
