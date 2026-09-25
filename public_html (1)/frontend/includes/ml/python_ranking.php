`<?php
// =============================================
// XGBOOST ML RANKING INTEGRATION
// File: includes/ml/python_ranking.php
// =============================================

// Fix path for database config
require_once __DIR__ . '/../config/database.php';

class XGBoostRankingSystem {
    private $python_api_url;
    
    public function __construct() {
        $this->python_api_url = 'http://localhost:5000';
    }
    
    /**
     * Call Python ML API
     */
    private function callPythonAPI($endpoint, $data = []) {
        $url = $this->python_api_url . $endpoint;
        
        $options = [
            'http' => [
                'header'  => "Content-type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($data),
                'timeout' => 30
            ],
        ];
        
        $context  = stream_context_create($options);
        
        try {
            $result = file_get_contents($url, false, $context);
            return json_decode($result, true);
        } catch (Exception $e) {
            error_log("Python XGBoost API Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get ranked candidates using XGBoost ML
     */
    public function getRankedCandidates($employer_id, $job_id = 0) {
        // First, ensure all applications have ML features
        $this->updateAllMLFeatures();
        
        // Call Python API for XGBoost ranking
        $result = $this->callPythonAPI('/rank-candidates', [
            'job_id' => $job_id,
            'employer_id' => $employer_id
        ]);
        
        if ($result && isset($result['ranked_candidates'])) {
            return $result['ranked_candidates'];
        }
        
        // Fallback to basic ranking if XGBoost API fails
        return $this->fallbackRanking($employer_id, $job_id);
    }
    
    /**
     * Update ML features for all applications
     */
    public function updateAllMLFeatures() {
        require_once __DIR__ . '/candidate_ranking.php';
        $ranking = new CandidateRanking();
        
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT application_id FROM applications");
        $stmt->execute();
        $applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        foreach ($applications as $app) {
            $ranking->updateMLRanking($app['application_id']);
        }
        
        $conn->close();
    }
    
    /**
     * Fallback ranking when XGBoost API is unavailable
     */
    private function fallbackRanking($employer_id, $job_id) {
        require_once __DIR__ . '/candidate_ranking.php';
        $ranking = new CandidateRanking();
        return $ranking->getRankedCandidates($employer_id, $job_id);
    }
    
    /**
     * Train the XGBoost ML model
     */
    public function trainModel() {
        $result = $this->callPythonAPI('/train', []);
        return $result ?: ['success' => false, 'message' => 'XGBoost API unavailable'];
    }
    
    /**
     * Check if XGBoost ML service is healthy
     */
    public function healthCheck() {
        try {
            $result = file_get_contents($this->python_api_url . '/health');
            $data = json_decode($result, true);
            return $data && isset($data['status']) && $data['status'] === 'healthy';
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check model training status
     */
    public function getModelStatus() {
        $result = $this->callPythonAPI('/model-status', []);
        return $result ?: ['model_trained' => false, 'training_samples' => 0];
    }
}

// Utility function to get ranked candidates
function getXGBoostRankedCandidates($employer_id, $job_id = 0) {
    $xgboost_ranking = new XGBoostRankingSystem();
    return $xgboost_ranking->getRankedCandidates($employer_id, $job_id);
}
?>