<?php
// Caminho: app/MercadoPagoService.php

require_once __DIR__ . '/Env.php';

class MercadoPagoService {
    private $accessToken;
    private $ambiente;
    private static $customTransport = null;

    private const REJECTION_MESSAGES = [
        'cc_rejected_insufficient_amount' => 'Saldo/limite insuficiente no cartão.',
        'cc_rejected_bad_filled_security_code' => 'Código de segurança (CVV) incorreto.',
        'cc_rejected_bad_filled_date' => 'Data de vencimento do cartão incorreta.',
        'cc_rejected_bad_filled_card_number' => 'Número do cartão inválido.',
        'cc_rejected_call_for_authorize' => 'Pagamento recusado. Entre em contato com a operadora do cartão para autorizar.',
        'cc_rejected_card_disabled' => 'O cartão informado está desativado.',
        'cc_rejected_duplicated_payment' => 'Pagamento duplicado. Aguarde alguns instantes antes de tentar novamente.',
        'cc_rejected_high_risk' => 'Pagamento recusado pela análise de segurança do Mercado Pago.',
        'cc_rejected_blacklist' => 'O cartão está bloqueado para esta transação.',
    ];

    public function __construct() {
        Env::load(__DIR__ . '/../.env');
        $this->ambiente = Env::getAmbiente();
        $this->accessToken = Env::get('MERCADO_PAGO_ACCESS_TOKEN');

        if (!$this->hasValidCredentials()) {
            error_log("MercadoPagoService [{$this->ambiente}]: MERCADO_PAGO_ACCESS_TOKEN ausente ou inválido.");
        }
    }

    /**
     * Permite injetar transporte HTTP customizado para testes automatizados.
     * Assinatura: function(string $method, string $url, array $headers, ?array $payload): array{http_code:int, body:?string, error:?string}
     */
    public static function setTransport(?callable $transport = null) {
        self::$customTransport = $transport;
    }

    /**
     * Valida se o token de acesso está configurado e compatível com o ambiente.
     */
    public function hasValidCredentials() {
        $missing = Env::validateRequired(['MERCADO_PAGO_ACCESS_TOKEN']);
        if (!empty($missing)) {
            return false;
        }
        if ($this->ambiente === 'producao' && strpos((string) $this->accessToken, 'TEST-') === 0) {
            error_log("MercadoPagoService [producao]: Credencial de teste detectada em ambiente de produção.");
            return false;
        }
        return true;
    }

    /**
     * Gera uma chave de idempotência determinística ou única para uma operação de negócio.
     *
     * @param string $resourceType Tipo do recurso ('fatura' ou 'pedido')
     * @param int|string $resourceId Identificador do recurso
     * @param string $context Contexto adicional opcional
     * @return string
     */
    public static function buildIdempotencyKey($resourceType, $resourceId, $context = '') {
        $prefix = preg_replace('/[^a-z0-9_]/i', '', (string) $resourceType);
        $seed = $prefix . ':' . $resourceId . ':' . ($context !== '' ? $context : bin2hex(random_bytes(8)));
        return sprintf('mp_%s_%s_%s', $prefix, $resourceId, substr(hash('sha256', $seed), 0, 24));
    }

    /**
     * Cria um pagamento no Mercado Pago (Checkout Transparente)
     *
     * @param array $paymentData Dados do pagamento
     * @param string|null $idempotencyKey Chave idempotente persistida antes da chamada
     * @return array Resposta padronizada da integração
     */
    public function createPayment($paymentData, $idempotencyKey = null) {
        if (!$this->hasValidCredentials()) {
            return [
                'success' => false,
                'status' => 500,
                'error_type' => 'config',
                'payment_status' => null,
                'message' => 'Serviço de pagamento indisponível por configuração de ambiente.',
                'idempotency_key' => $idempotencyKey,
                'response' => ['message' => 'Credenciais do Mercado Pago não configuradas.']
            ];
        }

        $url = "https://api.mercadopago.com/v1/payments";
        $resolvedKey = !empty($idempotencyKey)
            ? trim((string) $idempotencyKey)
            : self::buildIdempotencyKey('payment', (string) ($paymentData['external_reference'] ?? 'avulso'));

        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer " . $this->accessToken,
            "X-Idempotency-Key: " . $resolvedKey
        ];

        $raw = $this->executeRequest('POST', $url, $headers, $paymentData);

        if (!empty($raw['error'])) {
            error_log("MercadoPagoService Transport Error [createPayment]: ref=" . ($paymentData['external_reference'] ?? 'n/a'));
            return [
                'success' => false,
                'status' => 0,
                'error_type' => 'transport',
                'payment_status' => null,
                'message' => 'Falha de comunicação com o provedor de pagamento. Tente novamente em instantes.',
                'idempotency_key' => $resolvedKey,
                'response' => ['error' => 'Falha de transporte ao comunicar com o Mercado Pago.']
            ];
        }

        $httpCode = (int) ($raw['http_code'] ?? 500);
        $decoded = is_string($raw['body']) ? json_decode($raw['body'], true) : (is_array($raw['body']) ? $raw['body'] : null);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        if ($httpCode >= 400 || $httpCode < 200) {
            $msg = $decoded['message'] ?? 'Erro ao processar pagamento junto ao provedor.';
            if (isset($decoded['cause']) && is_array($decoded['cause']) && count($decoded['cause']) > 0) {
                $causeDesc = $decoded['cause'][0]['description'] ?? ($decoded['cause'][0]['code'] ?? '');
                if ($causeDesc !== '') {
                    $msg .= ' - ' . $causeDesc;
                }
            }
            $decoded['message'] = $msg;
            error_log("MercadoPagoService HTTP Error [createPayment]: http={$httpCode} ref=" . ($paymentData['external_reference'] ?? 'n/a'));
            return [
                'success' => false,
                'status' => $httpCode,
                'error_type' => 'http',
                'payment_status' => $decoded['status'] ?? null,
                'message' => $msg,
                'idempotency_key' => $resolvedKey,
                'response' => $decoded
            ];
        }

        $paymentStatus = $decoded['status'] ?? 'rejected';
        $statusDetail = $decoded['status_detail'] ?? '';

        if ($paymentStatus === 'rejected' || $paymentStatus === 'cancelled') {
            $msg = self::REJECTION_MESSAGES[$statusDetail] ?? 'Transação recusada pela operadora do cartão.';
            $decoded['message'] = $msg;
            return [
                'success' => false,
                'status' => $httpCode,
                'error_type' => 'rejected',
                'payment_status' => $paymentStatus,
                'message' => $msg,
                'idempotency_key' => $resolvedKey,
                'response' => $decoded
            ];
        }

        return [
            'success' => in_array($paymentStatus, ['approved', 'pending', 'in_process', 'authorized'], true),
            'status' => $httpCode,
            'error_type' => in_array($paymentStatus, ['approved', 'pending', 'in_process', 'authorized'], true) ? null : 'rejected',
            'payment_status' => $paymentStatus,
            'message' => $paymentStatus === 'approved' ? 'Pagamento aprovado.' : 'Pagamento em processamento.',
            'idempotency_key' => $resolvedKey,
            'response' => $decoded
        ];
    }

    /**
     * Consulta os detalhes de um pagamento pelo ID
     *
     * @param string|int $paymentId ID do pagamento no Mercado Pago
     * @return array Resposta padronizada da API
     */
    public function getPayment($paymentId) {
        if (!$this->hasValidCredentials()) {
            return [
                'success' => false,
                'status' => 500,
                'error_type' => 'config',
                'payment_status' => null,
                'message' => 'Serviço de pagamento indisponível por configuração de ambiente.',
                'response' => ['message' => 'Credenciais do Mercado Pago não configuradas.']
            ];
        }

        $cleanId = preg_replace('/[^0-9]/', '', (string) $paymentId);
        $url = "https://api.mercadopago.com/v1/payments/" . $cleanId;

        $headers = [
            "Authorization: Bearer " . $this->accessToken
        ];

        $raw = $this->executeRequest('GET', $url, $headers, null);

        if (!empty($raw['error'])) {
            error_log("MercadoPagoService Transport Error [getPayment]: id={$cleanId}");
            return [
                'success' => false,
                'status' => 0,
                'error_type' => 'transport',
                'payment_status' => null,
                'message' => 'Falha de comunicação ao consultar pagamento.',
                'response' => ['error' => 'Falha de transporte ao consultar pagamento.']
            ];
        }

        $httpCode = (int) ($raw['http_code'] ?? 500);
        $decoded = is_string($raw['body']) ? json_decode($raw['body'], true) : (is_array($raw['body']) ? $raw['body'] : null);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        return [
            'success' => ($httpCode >= 200 && $httpCode < 300),
            'status' => $httpCode,
            'error_type' => ($httpCode >= 200 && $httpCode < 300) ? null : 'http',
            'payment_status' => $decoded['status'] ?? null,
            'message' => ($httpCode >= 200 && $httpCode < 300) ? 'OK' : ($decoded['message'] ?? 'Pagamento não encontrado.'),
            'response' => $decoded
        ];
    }

    /**
     * Executa requisição HTTP via transporte injetado (testes) ou cURL (produção/sandbox).
     */
    private function executeRequest($method, $url, array $headers, $payload = null) {
        if (self::$customTransport !== null) {
            return call_user_func(self::$customTransport, $method, $url, $headers, $payload);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'http_code' => $httpCode,
            'body' => $response !== false ? $response : null,
            'error' => $error !== '' ? $error : null
        ];
    }
}
?>
