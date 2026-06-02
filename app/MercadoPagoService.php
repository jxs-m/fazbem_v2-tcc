<?php
// Caminho: app/MercadoPagoService.php

require_once __DIR__ . '/Env.php';

class MercadoPagoService {
    private $accessToken;

    public function __construct() {
        
        Env::load(__DIR__ . '/../.env');
        
        $this->accessToken = getenv('MERCADO_PAGO_ACCESS_TOKEN');
        
        if (!$this->accessToken) {
            error_log("Mercado Pago Access Token não encontrado no ambiente.");
        }
    }

    /**
     * Cria um pagamento no Mercado Pago (Checkout Transparente)
     * @param array $paymentData Dados do pagamento (token, transaction_amount, payment_method_id, payer, etc)
     * @return array Resposta da API com HTTP status e o corpo decodificado
     */
    public function createPayment($paymentData) {
        $url = "https://api.mercadopago.com/v1/payments";

        // Chave de idempotência única para evitar pagamentos duplicados
        $idempotencyKey = uniqid("mp_", true);

        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer " . $this->accessToken,
            "X-Idempotency-Key: " . $idempotencyKey
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
       
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            return [
                'status' => 500,
                'response' => ['error' => 'cURL Error: ' . $error]
            ];
        }

        $decoded = json_decode($response, true);
        
        // Extrai a causa real do erro para debugar
        if ($httpCode >= 400 && isset($decoded['cause']) && count($decoded['cause']) > 0) {
            $msg = $decoded['message'] ?? 'internal_error';
            $cause = $decoded['cause'][0]['description'] ?? json_encode($decoded['cause']);
            $decoded['message'] = $msg . ' - ' . $cause;
        } else if ($httpCode >= 400 && !isset($decoded['message'])) {
            $decoded['message'] = $response; 
        }

        return [
            'status' => $httpCode,
            'response' => $decoded
        ];
    }

    /**
     * Cria um pedido na API de Orders (opcional dependendo do fluxo exato)
     */
    public function createOrder($orderData) {
        $url = "https://api.mercadopago.com/merchant_orders";

        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer " . $this->accessToken
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $decoded = json_decode($result, true);

        // Se der erro, retornar o body cru para debug
        if ($http_status >= 400 && isset($decoded['cause']) && count($decoded['cause']) > 0) {
            $msg = $decoded['message'] ?? 'internal_error';
            $cause = $decoded['cause'][0]['description'] ?? json_encode($decoded['cause']);
            $decoded['message'] = $msg . ' - ' . $cause;
        } else if ($http_status >= 400 && !isset($decoded['message'])) {
            $decoded['message'] = $result; 
        }

        return [
            'status' => $http_status,
            'response' => $decoded
        ];
    }
}
?>
