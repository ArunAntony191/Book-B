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
        } 
        elseif ($newStatus === 'delivered') { // Delivering
            if ($currentStatus !== 'active') {
                throw new Exception("Invalid transition: Order must be 'active' (in transit) before delivery.");
            }
            $sql = "UPDATE transactions SET status = 'delivered', delivered_at = NOW() WHERE id = ?";
        }
        else {
            throw new Exception("Invalid status update requested.");
        }

        $this->pdo->prepare($sql)->execute([$transactionId]);

        // Send notifications
        $this->sendUpdateNotification($tx, $newStatus);

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
            $msg = "Delivered! '{$tx['title']}' has arrived.";
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

        // If not already 'delivered', maybe auto-set it? 
        // User confirmation overrides agent status.
        if ($tx['status'] !== 'delivered') {
             $this->pdo->prepare("UPDATE transactions SET status = 'delivered', delivered_at = NOW() WHERE id = ?")
                 ->execute([$transactionId]);
             
             // Also notify Lender that it's done
             $msg = "Borrower confirmed receipt of '{$tx['title']}'. Transaction Complete.";
             $this->pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, ?, ?, ?)")
                 ->execute([$tx['lender_id'], 'receipt_confirmed', $msg, $transactionId]);
        }

        return true;
    }
}
?>
