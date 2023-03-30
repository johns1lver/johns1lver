<?php
require_once('johns1lver/BeforeValidException.php');
require_once('johns1lver/ExpiredException.php');
require_once('johns1lver/SignatureInvalidException.php');
require_once('johns1lver/JWT.php');

use \Firebase\JWT\JWT;

// Здесь указываем ключ и секрет для авторизации в API Zoom
$key = 'your_api_key';
$secret = 'your_api_secret';

// Здесь указываем ID конференции, в которой будет работать бот
$meeting_id = 'your_meeting_id';

// Здесь указываем, сколько разрешено отправлять одно и то же сообщение в чат
$max_repeats = 1;

// Парсим входные данные, полученные от Zoom API
$payload = file_get_contents('php://input');
$json = json_decode($payload, true);

// Если данные содержат информацию о событии "Message Received", обрабатываем сообщение
if ($json['event'] == 'message_received') {
    // Извлекаем из данных текст сообщения и ID пользователя, который отправил сообщение
    $message = $json['payload']['message'];
    $user_id = $json['payload']['user']['id'];

    // Подготавливаем данные для отправки запроса к API Zoom
    $zoom_time = time() * 1000;
    $zoom_data = array(
        'iss' => $key,
        'exp' => $zoom_time + 60,
        'iat' => $zoom_time,
        'jti' => md5($key . $zoom_time)
    );
    $zoom_token = JWT::encode($zoom_data, $secret);

    // Отправляем запрос на получение списка сообщений в чате
    $url = "https://api.zoom.us/v2/metrics/meetings/{$meeting_id}/participants/{$user_id}/chat/messages";
    $headers = array(
        "Authorization: Bearer {$zoom_token}",
        'Content-Type: application/json',
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    // Если запрос успешен и получен список сообщений, проверяем, сколько раз было отправлено это же сообщение
    if ($result) {
        $messages = json_decode($result, true);
        $count = 0;
        foreach ($messages['messages'] as $msg) {
            if ($msg['message'] == $message) {
                $count++;
            }
        }
        // Если количество повторений сообщения превышает допустимый порог, баним пользователя
        if ($count > $max_repeats) {
            $url = "https://api.zoom.us/v2/metrics/meetings/{$meeting_id}/participants/{$user_id}/status";
            $data = array(
                'action' => 'put',
                'host_id' => 'your_host_id',
                'participant_id' => $user_id,
                'status' => 'remove'
            );
            $headers = array(
                "Authorization: Bearer {$
