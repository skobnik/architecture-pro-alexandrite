<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use GuzzleHttp\Client;

// Получаем глобальный Tracer (автоматически настраивается из ENV переменных)
$tracer = Globals::tracerProvider()->getTracer('service-a');

// Маршрутизация
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

if ($requestUri === '/' && $requestMethod === 'GET') {
    handleRequest($tracer);
} elseif ($requestUri === '/health' && $requestMethod === 'GET') {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'healthy', 'service' => 'service-a']);
} else {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not found']);
}

/**
 * Обработка запроса к главной странице
 */
function handleRequest($tracer): void
{
    // Создаем корневой спан для входящего запроса
    $span = $tracer->spanBuilder('GET /')
        ->setSpanKind(SpanKind::KIND_SERVER)
        ->setAttribute('http.method', 'GET')
        ->setAttribute('http.route', '/')
        ->setAttribute('http.client_ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown')
        ->setAttribute('http.user_agent', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown')
        ->startSpan();
    
    $scope = $span->activate();
    
    try {
        // Логируем событие начала обработки
        $span->addEvent('Processing order request started');
        
        // Имитация работы с БД
        usleep(50000); // 50ms
        
        $span->addEvent('Orders fetched from database');
        
        // Вызываем сервис B
        $calculation = callServiceB($tracer);
        
        $span->addEvent('Calculation received from service-b');
        
        // Формируем ответ
        $response = [
            'service' => 'service-a',
            'timestamp' => date('Y-m-d H:i:s'),
            'orders' => [
                ['id' => 1, 'customer' => 'Иван Петров', 'total' => 15000],
                ['id' => 2, 'customer' => 'Мария Сидорова', 'total' => 25000],
            ],
            'calculation' => $calculation
        ];
        
        $span->setAttribute('http.status_code', 200);
        $span->setAttribute('orders.count', count($response['orders']));
        
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } catch (Throwable $e) {
        $span->recordException($e);
        $span->setAttribute('http.status_code', 500);
        $span->setAttribute('error.message', $e->getMessage());
        
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    } finally {
        $scope->detach();
        $span->end();
    }
}

/**
 * Вызов сервиса B для расчета
 */
function callServiceB($tracer) {
    $client = new Client([
        'base_uri' => 'http://service-b:8080',
        'timeout' => 5.0,
    ]);
    
    // Создаем дочерний спан для вызова сервиса B
    $span = $tracer->spanBuilder('Call service-b')
        ->setSpanKind(SpanKind::KIND_CLIENT)
        ->setParent(Context::getCurrent())
        ->setAttribute('http.method', 'GET')
        ->setAttribute('http.url', 'http://service-b:8080/calculate')
        ->setAttribute('peer.service', 'service-b')
        ->startSpan();
    
    $scope = $span->activate();
    
    try {
        $span->addEvent('Sending request to service-b');
        
        $response = $client->get('/calculate');
        
        // ИСПРАВЛЕНИЕ: получаем содержимое как строку
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);
        
        $span->setAttribute('http.status_code', $response->getStatusCode());
        $span->addEvent('Response received from service-b');
        
        return $data;
        
    } catch (Throwable $e) {
        $span->recordException($e);
        $span->setAttribute('error.message', $e->getMessage());
        
        return [
            'error' => 'Calculation service unavailable',
            'message' => $e->getMessage()
        ];
    } finally {
        $scope->detach();
        $span->end();
    }
}