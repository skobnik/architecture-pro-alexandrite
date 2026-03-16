<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;

$tracer = Globals::tracerProvider()->getTracer('service-b');

// Маршрутизация
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

if ($requestUri === '/calculate' && $requestMethod === 'GET') {
    handleCalculate($tracer);
} elseif ($requestUri === '/health' && $requestMethod === 'GET') {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'healthy', 'service' => 'service-b']);
} else {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not found']);
}

/**
 * Обработка запроса на расчет
 */
function handleCalculate($tracer): void
{
    // Создаем спан для входящего запроса
    $span = $tracer->spanBuilder('GET /calculate')
        ->setSpanKind(SpanKind::KIND_SERVER)
        ->setAttribute('http.method', 'GET')
        ->setAttribute('http.route', '/calculate')
        ->startSpan();
    
    $scope = $span->activate();
    
    try {
        $span->addEvent('Starting price calculation');
        
        // Имитация сложных вычислений (3 шага)
        $steps = 3;
        for ($i = 0; $i < $steps; $i++) {
            usleep(100000); // 100ms на шаг
            $span->addEvent('Calculation step ' . ($i + 1) . ' completed');
        }
        
        // Генерируем результат
        $result = [
            'service' => 'service-b',
            'timestamp' => date('Y-m-d H:i:s'),
            'calculation_id' => uniqid('calc_', true),
            'base_price' => 10000,
            'material_cost' => 5000,
            'labor_cost' => 3000,
            'tax' => 1800,
            'total' => 19800,
            'currency' => 'RUB'
        ];
        
        $span->setAttribute('http.status_code', 200);
        $span->setAttribute('calculation.total', $result['total']);
        $span->setAttribute('calculation.id', $result['calculation_id']);
        
        $span->addEvent('Calculation completed successfully');
        
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } catch (Throwable $e) {
        $span->recordException($e);
        $span->setAttribute('http.status_code', 500);
        $span->setAttribute('error.message', $e->getMessage());
        
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Calculation failed']);
    } finally {
        $scope->detach();
        $span->end();
    }
}