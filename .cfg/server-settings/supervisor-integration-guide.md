# Supervisor 통합 가이드

## 1. 데이터베이스 구조

### kb_server_processes 테이블 예시
```sql
CREATE TABLE IF NOT EXISTS kb_server_processes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    process_name VARCHAR(100) UNIQUE NOT NULL,  -- 예: domaeka-test-01
    port INT NOT NULL,
    pid INT DEFAULT NULL,                       -- 프로세스 ID
    status ENUM('stopped', 'starting', 'running', 'stopping', 'error') DEFAULT 'stopped',
    supervisor_host VARCHAR(100),               -- Supervisor 호스트 (docker 서비스명)
    supervisor_port INT,                        -- Supervisor 웹 UI 포트 (9101-9113)
    last_heartbeat DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## 2. Python 서버 수정 사항

### main.py에 추가할 코드
```python
import os
import signal
import asyncio
from datetime import datetime

class ServerProcess:
    def __init__(self, process_name):
        self.process_name = process_name
        self.pid = os.getpid()
        self.port = None
        
    async def register_process(self):
        """프로세스 시작 시 DB에 PID 등록"""
        # DB에서 포트 정보 조회
        process_info = await db.fetchone(
            "SELECT port, supervisor_host, supervisor_port FROM kb_server_processes WHERE process_name = ?",
            (self.process_name,)
        )
        
        if not process_info:
            raise ValueError(f"Process {self.process_name} not found in database")
        
        self.port = process_info['port']
        
        # PID 및 상태 업데이트
        await db.execute(
            """UPDATE kb_server_processes 
               SET pid = ?, status = 'running', last_heartbeat = NOW() 
               WHERE process_name = ?""",
            (self.pid, self.process_name)
        )
        
        # 정상 종료 시그널 핸들러 등록
        signal.signal(signal.SIGTERM, self._handle_shutdown)
        signal.signal(signal.SIGINT, self._handle_shutdown)
        
        # 하트비트 태스크 시작
        asyncio.create_task(self._heartbeat_loop())
        
        return self.port
    
    async def _heartbeat_loop(self):
        """주기적으로 상태 업데이트"""
        while True:
            try:
                await db.execute(
                    "UPDATE kb_server_processes SET last_heartbeat = NOW() WHERE process_name = ?",
                    (self.process_name,)
                )
                await asyncio.sleep(30)  # 30초마다 하트비트
            except Exception as e:
                logging.error(f"Heartbeat error: {e}")
                await asyncio.sleep(5)
    
    def _handle_shutdown(self, signum, frame):
        """종료 시그널 처리"""
        asyncio.create_task(self._cleanup())
    
    async def _cleanup(self):
        """종료 시 정리 작업"""
        await db.execute(
            "UPDATE kb_server_processes SET pid = NULL, status = 'stopped' WHERE process_name = ?",
            (self.process_name,)
        )
        asyncio.get_event_loop().stop()
```

## 3. 웹 관리자에서 Supervisor 제어

### PHP 코드 예시
```php
// dmk/admin/server_control.php

class SupervisorControl {
    private $processes = [
        'domaeka-test-01' => ['host' => 'domaeka-server-test-01', 'port' => 9101],
        'domaeka-test-02' => ['host' => 'domaeka-server-test-02', 'port' => 9102],
        'domaeka-test-03' => ['host' => 'domaeka-server-test-03', 'port' => 9103],
        'domaeka-live-01' => ['host' => 'domaeka-server-live-01', 'port' => 9111],
        'domaeka-live-02' => ['host' => 'domaeka-server-live-02', 'port' => 9112],
        'domaeka-live-03' => ['host' => 'domaeka-server-live-03', 'port' => 9113],
    ];
    
    public function getProcessStatus($processName) {
        $config = $this->processes[$processName];
        $url = "http://{$config['host']}:{$config['port']}/RPC2";
        
        $request = xmlrpc_encode_request("supervisor.getProcessInfo", ["python-server"]);
        
        $context = stream_context_create([
            'http' => [
                'method' => "POST",
                'header' => [
                    "Content-Type: text/xml",
                    "Authorization: Basic " . base64_encode("admin:!rhksflwk@.")
                ],
                'content' => $request
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        return xmlrpc_decode($response);
    }
    
    public function startProcess($processName) {
        return $this->_supervisorCall($processName, "supervisor.startProcess", ["python-server"]);
    }
    
    public function stopProcess($processName) {
        return $this->_supervisorCall($processName, "supervisor.stopProcess", ["python-server"]);
    }
    
    public function restartProcess($processName) {
        $this->stopProcess($processName);
        sleep(2);
        return $this->startProcess($processName);
    }
    
    private function _supervisorCall($processName, $method, $params) {
        $config = $this->processes[$processName];
        $url = "http://{$config['host']}:{$config['port']}/RPC2";
        
        $request = xmlrpc_encode_request($method, $params);
        
        $context = stream_context_create([
            'http' => [
                'method' => "POST",
                'header' => [
                    "Content-Type: text/xml",
                    "Authorization: Basic " . base64_encode("admin:!rhksflwk@.")
                ],
                'content' => $request
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        return xmlrpc_decode($response);
    }
}
```

## 4. 웹 관리자 UI 예시

```php
// 프로세스 목록 페이지
<?php
$supervisor = new SupervisorControl();

$processes = sql_query("SELECT * FROM kb_server_processes ORDER BY process_name");
?>

<table class="table">
    <thead>
        <tr>
            <th>프로세스명</th>
            <th>포트</th>
            <th>PID</th>
            <th>상태</th>
            <th>마지막 하트비트</th>
            <th>제어</th>
        </tr>
    </thead>
    <tbody>
    <?php while($row = sql_fetch_array($processes)) { 
        $supervisorStatus = $supervisor->getProcessStatus($row['process_name']);
    ?>
        <tr>
            <td><?php echo $row['process_name']; ?></td>
            <td><?php echo $row['port']; ?></td>
            <td><?php echo $row['pid'] ?: '-'; ?></td>
            <td>
                <span class="badge badge-<?php echo $supervisorStatus['statename'] == 'RUNNING' ? 'success' : 'danger'; ?>">
                    <?php echo $supervisorStatus['statename']; ?>
                </span>
            </td>
            <td><?php echo $row['last_heartbeat']; ?></td>
            <td>
                <button onclick="controlProcess('<?php echo $row['process_name']; ?>', 'start')" 
                        class="btn btn-sm btn-success" 
                        <?php echo $supervisorStatus['statename'] == 'RUNNING' ? 'disabled' : ''; ?>>
                    시작
                </button>
                <button onclick="controlProcess('<?php echo $row['process_name']; ?>', 'stop')" 
                        class="btn btn-sm btn-danger"
                        <?php echo $supervisorStatus['statename'] != 'RUNNING' ? 'disabled' : ''; ?>>
                    중지
                </button>
                <button onclick="controlProcess('<?php echo $row['process_name']; ?>', 'restart')" 
                        class="btn btn-sm btn-warning">
                    재시작
                </button>
            </td>
        </tr>
    <?php } ?>
    </tbody>
</table>

<script>
function controlProcess(processName, action) {
    if(confirm(`${processName} 프로세스를 ${action} 하시겠습니까?`)) {
        $.post('server_control_ajax.php', {
            process_name: processName,
            action: action
        }, function(response) {
            if(response.success) {
                alert('명령이 실행되었습니다.');
                location.reload();
            } else {
                alert('오류: ' + response.message);
            }
        }, 'json');
    }
}
</script>
```

## 5. Docker Compose 환경 변수 정리

```yaml
environment:
  - PYTHONUNBUFFERED=1
  - SERVER_NAME=domaeka-test-01  # main.py의 --name 파라미터로 전달
  # SERVER_PORT는 필요 없음 (DB에서 조회)
```

## 6. 구현 순서

1. **DB 테이블 수정**: supervisor_host, supervisor_port 필드 추가
2. **Python 서버 수정**: PID 등록 및 하트비트 구현
3. **Supervisor 설정**: XML-RPC 인터페이스 활성화 확인
4. **웹 관리자 구현**: Supervisor 제어 클래스 및 UI 구현
5. **테스트**: 각 프로세스 시작/중지/재시작 테스트

## 7. 주의사항

- Supervisor 웹 UI의 인증 정보는 안전하게 관리
- Docker 네트워크 내에서 호스트명으로 통신 가능
- 하트비트 타임아웃 시 알림 기능 추가 고려
- 프로세스 로그는 Supervisor와 Python 양쪽에서 관리

이 구조로 웹 관리자에서 각 프로세스를 효과적으로 제어할 수 있습니다.