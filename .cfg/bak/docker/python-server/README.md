# Python Server Docker 설정

## 구조
- `Dockerfile`: Python + Supervisor 이미지 정의
- `supervisord.conf`: Supervisor 설정
- `requirements.txt`: Python 패키지 목록

## 빌드 및 실행

```bash
# 이미지 빌드
cd /data/projects/domaeka
docker-compose -f .cfg/docker-compose-with-dockerfile.yml build

# 실행
docker-compose -f .cfg/docker-compose-with-dockerfile.yml up -d
```

## Supervisor 웹 UI 접속

각 서버별 Supervisor 웹 UI:
- Test-01: http://서버IP:9101 (admin/domaeka123)
- Test-02: http://서버IP:9102
- Test-03: http://서버IP:9103
- Live-01: http://서버IP:9111
- Live-02: http://서버IP:9112
- Live-03: http://서버IP:9113

## 웹 어드민 통합

PHP에서 Supervisor XML-RPC API 호출:

```php
<?php
// Supervisor 서버 상태 확인
$servers = [
    'test-01' => 'http://admin:domaeka123@localhost:9101/RPC2',
    'test-02' => 'http://admin:domaeka123@localhost:9102/RPC2',
    // ...
];

function getServerStatus($url) {
    $request = xmlrpc_encode_request("supervisor.getState", array());
    $context = stream_context_create(array('http' => array(
        'method' => "POST",
        'header' => "Content-Type: text/xml",
        'content' => $request
    )));
    
    $response = file_get_contents($url, false, $context);
    return xmlrpc_decode($response);
}

// 프로세스 시작/중지
function controlProcess($url, $action, $processName = 'python-server') {
    $method = "supervisor." . $action . "Process";
    $request = xmlrpc_encode_request($method, array($processName));
    // ... 실행 ...
}
```

## 명령어

```bash
# 특정 서버 로그 확인
docker exec domaeka-server-test-01 tail -f /var/log/supervisor/python-server.out.log

# Supervisor 상태 확인
docker exec domaeka-server-test-01 supervisorctl status

# 프로세스 재시작
docker exec domaeka-server-test-01 supervisorctl restart python-server
```