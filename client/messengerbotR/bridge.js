/**
 * MessengerBotR 클라이언트 - 카카오톡 브릿지 스크립트 v3.3.0
 * 
 * @description
 * 카카오톡과 서버 간의 통신을 중개하는 브릿지 클라이언트 스크립트입니다.
 * v3.3.0: JSON + Raw 데이터 구조로 대용량 미디어 처리 성능 극대화
 * 
 * @compatibility MessengerBotR v0.7.38a ~ v0.7.39a
 * @engine Rhino JavaScript Engine
 * 
 * @requirements
 * [필수 권한]
 * • 메신저봇R 처음 설치시 요구하는 권한들 모두 허용.
 * • 다른 앱 위에 표시 (MessengerBotR) : 미디어 전송 기능을 위해 필요!
 * • 사진 및 동영상 엑세스 권한 : "항상 모두 허용"(KakaoTalk)
 * • 배터리 사용 무제한 (MessengerBotR, KakaoTalk)
 * 
 * @restrictions
 * [개발 제약사항]
 * • const 키워드 사용 금지 (Rhino 엔진 제약)
 * • let 키워드 사용 금지 (Rhino 엔진 제약 발생할 우려 있음)
 * • 'Code has no side effects' 경고는 정상 동작 (무시 가능)
 * • 🔴 메신저봇R 앱 자체가 불안정한 요소가 많아 알 수 없는 문제를 발생할 수 있습니다.
 * 
 * @version 3.3.3
 * @author kkobot.com
 * @improvements 
 *   - v3.3.0: JSON + Raw 데이터 구조, 99.9% 파싱 부하 감소, Base64 인코딩 보안
 *   - v3.2.1: 서버 지정 미디어 전송 대기시간 지원 (media_wait_time)
 *   - v3.2.0: 강화된 핸드셰이크 인증 시스템, kb_bot_devices 테이블 연동
 */

// =============================================================================
// 1. 설정 모듈 (BOT_CONFIG) - v3.3.0 업데이트
// =============================================================================
var BOT_CONFIG = {
    // 기본 정보
    VERSION: '3.3.3',
    BOT_NAME: 'LOA.i',
    CLIENT_TYPE: 'MessengerBotR',
    PROTOCOL_VERSION: '3.3.3',  // 새 프로토콜 버전

    // 서버 및 인증 정보
    SECRET_KEY: "8vQw!@#4kLz9^&*1pXyZ2$%6sDq7!@#8vQw!@#4kLz9^&*1pXyZ2$%6sDq7!@#8",
    BOT_SPECIFIC_SALT: "7f$kLz9^&*1pXyZ2",
    SERVER_LIST: [
        { host: "100.69.44.56", port: 1491, priority: 5, name: "Dev.PC 1" },
        { host: "100.69.44.56", port: 1492, priority: 2, name: "Dev.PC 2" },
        { host: "100.73.137.47", port: 1491, priority: 3, name: "Dev.Laptop 1" },
        { host: "100.73.137.47", port: 1492, priority: 4, name: "Dev.Laptop 2" }
    ],

    // 동작 설정
    MAX_MESSAGE_LENGTH: 65000,
    BASE_RECONNECT_DELAY: 2000, // ms
    MAX_RECONNECT_DELAY: 60000, // ms
    MAX_RECONNECT_ATTEMPTS: -1, // 🔴 무한 재연결로 변경
    
    // TTL 설정
    MESSAGE_TTL: 30000, // 30초 (밀리초)

    // 미디어 처리 설정
    MEDIA_TEMP_DIR: "/storage/emulated/0/msgbot/server-media",
    FILE_PROVIDER_AUTHORITY: "com.xfl.msgbot.provider",
    KAKAOTALK_PACKAGE_NAME: "com.kakao.talk",
     
    // 컴파일 지연 방지 설정
    INITIALIZATION_DELAY: 1000, // initializeEventListeners 지연 시간

    // 🔴 새로운 장기 실행 안정성 설정
    ROOM_INACTIVE_DAYS: 30,             // 30일 이상 비활성 방 정리
    TEMP_FILE_MAX_AGE_DAYS: 7,          // 7일 이상 된 임시 파일 정리
    CLEANUP_INTERVAL: 86400000,         // 24시간마다 정리 (1일)
    MAX_QUEUE_SIZE: 2000,               // 극단적 상황 대비 큐 크기 제한
    THREAD_JOIN_TIMEOUT: 5000,          // 스레드 종료 대기 시간 (5초)

    // 🔴 모니터링 설정 추가
    MONITORING_ENABLED: true,           // 모니터링 기능 활성화
    
    // 🔴 로깅 설정 추가
    LOGGING: {
        CORE_MESSAGES: true,            // 핵심 메시지 로깅 (전송/수신/ping/연결)
        CONNECTION_EVENTS: true,        // 연결 관련 이벤트 로깅
        MESSAGE_TRANSFER: true,         // 메시지 전송/수신 로깅
        PING_EVENTS: true,              // ping 관련 이벤트 로깅
        QUEUE_OPERATIONS: true,         // 큐 처리 관련 로깅
        RESOURCE_INFO: true,            // 리소스 정보 로깅
        MESSAGE_CONTENT: true,          // 송수신 메시지 내용 표시 (요약)
        MESSAGE_CONTENT_DETAIL: true   // 송수신 메시지 전체 내용 표시 (전체, 디버깅용)
    },

    // 🔴 파일 전송 대기시간 설정 (사용자 조절 가능)
    // v3.2.1: 서버에서 media_wait_time 값을 전송하면 해당 값을 우선 사용합니다.
    // 서버 응답 예시: { "event": "messageResponse", "data": { "room": "방이름", "text": "MEDIA_URL:...", "media_wait_time": 8000 } }
    FILE_SEND_TIMING: {
        BASE_WAIT_TIME: 1500,
        SIZE_BASED_WAIT_PER_MB: 2000,
        COUNT_BASED_WAIT_PER_FILE: 300,
        SINGLE_FILE: {
            MIN_WAIT: 4000,
            MAX_WAIT: 6000
        },
        MULTI_FILE: {
            MIN_WAIT: 3000,
            MAX_WAIT: 15000
        }
    },

    // v3.3.0: 메시지 타입 정의
    MESSAGE_TYPES: {
        TEXT: "text",
        IMAGE: "image", 
        AUDIO: "audio",
        VIDEO: "video",
        DOCUMENT: "document"
    },

    // 안드로이드 패키지 (중앙 관리)
    PACKAGES: {
        Intent: Packages.android.content.Intent,
        Uri: Packages.android.net.Uri,
        File: Packages.java.io.File,
        Long: Packages.java.lang.Long,
        Integer: Packages.java.lang.Integer,
        URL: Packages.java.net.URL,
        MediaScannerConnection: Packages.android.media.MediaScannerConnection,
        ArrayList: Packages.java.util.ArrayList,
        FileProvider: Packages.androidx.core.content.FileProvider,
        StrictMode: Packages.android.os.StrictMode,
        FileOutputStream: java.io.FileOutputStream,
        Base64: android.util.Base64,
        System: java.lang.System
    },

    // 연결 관리 (기존 설정 유지)
    HEARTBEAT_INTERVAL: 30000,
    RECONNECT_INTERVALS: [1000, 2000, 5000, 10000, 30000, 60000],
    SOCKET_TIMEOUT: 10000,

    // 메시지 관리 (기존 설정 유지)
    MESSAGE_QUEUE_SIZE: 100,
    PENDING_MESSAGE_TIMEOUT: 300000,
    PENDING_MESSAGE_CLEANUP_INTERVAL: 60000,

    // 자원 관리 (기존 설정 유지)
    MEMORY_CLEANUP_INTERVAL: 300000,
    GC_INTERVAL: 600000,

    // 디버그 설정 (기존 설정 유지)
    DEBUG: false,
    LOG_LEVEL: 'INFO'
};

// =============================================================================
// 2. 유틸리티 모듈 (Utils) - v3.3.0 업데이트
// =============================================================================
var Utils = (function() {
    function generateUniqueId() {
        try {
            if (typeof Security !== 'undefined' && Security.ulid) {
                return Security.ulid();
            }
        } catch (e) {}
        return Date.now().toString(36) + Math.random().toString(36).substring(2);
    }

    // v3.3.0: UTC 타임스탬프 생성
    function formatTimestamp(dateObj) {
        if (!(dateObj instanceof Date)) return '';
        return dateObj.toISOString(); // UTC 형식: 2025-07-21T12:12:11.000Z
    }

    function sanitizeText(text) {
        if (!text) return '';
        if (text.length > BOT_CONFIG.MAX_MESSAGE_LENGTH) {
            Log.e("[VALIDATION] 메시지 길이 초과! → 잘림");
            text = text.substring(0, BOT_CONFIG.MAX_MESSAGE_LENGTH);
        }
        text = text.replace(/[\u0000-\u0009\u000B\u000C\u000E-\u001F\u007F]/g, '');
        text = text.replace(/[\u202A-\u202E\u2066-\u2069]/g, '');
        return text;
    }

    // v3.3.0: Base64 인코딩/디코딩 유틸리티
    function base64Encode(text) {
        try {
            var bytes = new java.lang.String(text).getBytes("UTF-8");
            return BOT_CONFIG.PACKAGES.Base64.encodeToString(bytes, BOT_CONFIG.PACKAGES.Base64.NO_WRAP);
        } catch (e) {
            Log.e("[BASE64] 인코딩 실패: " + e);
            return text;
        }
    }

    function base64Decode(base64Text) {
        try {
            var bytes = BOT_CONFIG.PACKAGES.Base64.decode(base64Text, BOT_CONFIG.PACKAGES.Base64.DEFAULT);
            return new java.lang.String(bytes, "UTF-8");
        } catch (e) {
            Log.e("[BASE64] 디코딩 실패: " + e);
            return base64Text;
        }
    }

    return {
        generateUniqueId: generateUniqueId,
        formatTimestamp: formatTimestamp,
        sanitizeText: sanitizeText,
        base64Encode: base64Encode,
        base64Decode: base64Decode
    };
})();

// =============================================================================
// 3. 디바이스 정보 모듈 (DeviceInfo) - 새로 추가
// =============================================================================
var DeviceInfo = (function() {
    var socketRef; // BotCore에서 설정할 소켓 참조

    // 🔴 Android ID 가져오기
    function _getAndroidId() {
        try {
            return android.provider.Settings.Secure.getString(
                android.app.ActivityThread.currentApplication().getContentResolver(),
                android.provider.Settings.Secure.ANDROID_ID
            );
        } catch (e) {
            Log.e("[DEVICE] Android ID 가져오기 실패: " + e);
            return "unknown";
        }
    }

    // 🔴 클라이언트 IP 주소 가져오기 (소켓 기반)
    function _getClientIP() {
        try {
            if (socketRef && socketRef.isConnected()) {
                return socketRef.getLocalAddress().getHostAddress();
            }
        } catch (e) {
            Log.e("[DEVICE] 클라이언트 IP 가져오기 실패: " + e);
        }
        return "unknown";
    }

    // 🔴 디바이스 정보 생성
    function _getDeviceInfo() {
        try {
            var model = android.os.Build.MODEL || "unknown";
            var brand = android.os.Build.BRAND || "unknown";
            var version = android.os.Build.VERSION.RELEASE || "unknown";
            var sdk = android.os.Build.VERSION.SDK_INT || "unknown";
            
            return brand + " " + model + " (Android " + version + ", API " + sdk + ")";
        } catch (e) {
            Log.e("[DEVICE] 디바이스 정보 가져오기 실패: " + e);
            return "unknown device";
        }
    }

    function setSocket(socket) {
        socketRef = socket;
    }

    function createHandshakeData() {
        return {
            clientType: BOT_CONFIG.CLIENT_TYPE,
            botName: BOT_CONFIG.BOT_NAME,
            version: BOT_CONFIG.VERSION,
            deviceID: _getAndroidId(),
            deviceIP: _getClientIP(),
            deviceInfo: _getDeviceInfo()
        };
    }

    function getAndroidId() {
        return _getAndroidId();
    }

    function getClientIP() {
        return _getClientIP();
    }

    function getDeviceInfo() {
        return _getDeviceInfo();
    }

    return {
        setSocket: setSocket,
        createHandshakeData: createHandshakeData,
        getAndroidId: getAndroidId,
        getClientIP: getClientIP,
        getDeviceInfo: getDeviceInfo
    };
})();

// =============================================================================
// 4. 인증 모듈 (Auth) - 업데이트
// =============================================================================
var Auth = (function() {
    var socketRef; // BotCore에서 설정할 소켓 참조

    function _getDeviceUUID() { try { return Device.getAndroidId(); } catch (e) { return "unknown"; } }
    function _getMacAddress() { try { var wm = Api.getContext().getSystemService(android.content.Context.WIFI_SERVICE); return wm.getConnectionInfo().getMacAddress(); } catch (e) { return "unknown"; } }
    function _getLocalIP() { try { if (socketRef && socketRef.isConnected()) { return socketRef.getLocalAddress().getHostAddress(); } } catch (e) {} return "unknown"; }

    function _generateHMAC(data, key) {
        try {
            var Mac = javax.crypto.Mac.getInstance("HmacSHA256");
            var secretKeySpec = new javax.crypto.spec.SecretKeySpec(new java.lang.String(key).getBytes("UTF-8"), "HmacSHA256");
            Mac.init(secretKeySpec);
            var bytes = Mac.doFinal(new java.lang.String(data).getBytes("UTF-8"));
            var result = [];
            for (var i = 0; i < bytes.length; i++) { result.push(("0" + (bytes[i] & 0xFF).toString(16)).slice(-2)); }
            return result.join("");
        } catch (e) {
            Log.e("HMAC 생성 실패: " + e);
            return "";
        }
    }

    function createAuthData() {
        var auth = {
            clientType: BOT_CONFIG.CLIENT_TYPE,
            botName: BOT_CONFIG.BOT_NAME,
            deviceUUID: _getDeviceUUID(),
            deviceID: DeviceInfo.getAndroidId(),
            macAddress: _getMacAddress(),
            ipAddress: _getLocalIP(),
            timestamp: Date.now(),
            version: BOT_CONFIG.VERSION
        };
        var signString = [BOT_CONFIG.CLIENT_TYPE, auth.botName, auth.deviceUUID, auth.macAddress, auth.ipAddress, auth.timestamp, BOT_CONFIG.BOT_SPECIFIC_SALT].join('|');
        auth.signature = _generateHMAC(signString, BOT_CONFIG.SECRET_KEY);
        return auth;
    }

    function setSocket(socket) {
        socketRef = socket;
    }

    return { 
        createAuthData: createAuthData, 
        setSocket: setSocket
    };
})();

// =============================================================================
// 5. 미디어 핸들러 모듈 (MediaHandler) - v3.3.0 업데이트
// =============================================================================
var MediaHandler = (function() {
    var P = BOT_CONFIG.PACKAGES;
    var context = App.getContext();

    var MIME_TYPES = {
        "jpg": "image/jpeg", "jpeg": "image/jpeg", "png": "image/png", "gif": "image/gif", "webp": "image/webp",
        "mp4": "video/mp4", "mov": "video/quicktime", "avi": "video/x-msvideo",
        "mp3": "audio/mpeg", "wav": "audio/wav", "m4a": "audio/mp4",
        "pdf": "application/pdf", "txt": "text/plain", "doc": "application/msword", "zip": "application/zip"
    };

    function _extractFileExtension(filePath) {
        var lastDot = filePath.lastIndexOf(".");
        return (lastDot !== -1 && lastDot < filePath.length - 1) ? filePath.substring(lastDot + 1).toLowerCase() : "bin";
    }

    function _determineMimeType(extension) { return MIME_TYPES[extension] || "application/octet-stream"; }

    function _scanMedia(filePath) { try { P.MediaScannerConnection.scanFile(context, [filePath], null, null); } catch (e) { Log.e("[SCAN] 미디어 스캔 실패: " + e); } }

    function _downloadFromUrl(url, targetPath) {
        var inputStream = null, outputStream = null;
        try {
            var conn = new P.URL(url).openConnection();
            conn.setConnectTimeout(30000); conn.setReadTimeout(30000);
            inputStream = conn.getInputStream();
            outputStream = new P.FileOutputStream(new P.File(targetPath));
            var buffer = java.lang.reflect.Array.newInstance(java.lang.Byte.TYPE, 8192);
            var bytesRead;
            while ((bytesRead = inputStream.read(buffer)) !== -1) { outputStream.write(buffer, 0, bytesRead); }
            return true;
        } catch (e) {
            Log.e("[DOWNLOAD] 실패: " + e); return false;
        } finally {
            if (outputStream) try { outputStream.close(); } catch (e) {}
            if (inputStream) try { inputStream.close(); } catch (e) {}
        }
    }

    function _saveBase64ToFile(base64Data, index) {
        try {
            var fileName = "media_" + Date.now() + "_" + Utils.generateUniqueId() + "_" + index + ".png";
            var filePath = BOT_CONFIG.MEDIA_TEMP_DIR + "/" + fileName;
            var folder = new P.File(BOT_CONFIG.MEDIA_TEMP_DIR);
            if (!folder.exists()) folder.mkdirs();
            var file = new P.File(filePath);
            var bytes = P.Base64.decode(base64Data, P.Base64.DEFAULT);
            var fos = new P.FileOutputStream(file);
            fos.write(bytes); fos.close();
            _scanMedia(filePath);
            return filePath;
        } catch (e) {
            Log.e("[Base64] 파일 저장 실패: " + e); return null;
        }
    }

    function _prepareFile(source, index) {
        var isUrl = String(source).toLowerCase().startsWith("http");
        var isBase64 = !isUrl && String(source).match(/^[A-Za-z0-9+\/=]+$/);
        var targetPath = "";
        var downloaded = false;

        if (isUrl) {
            var ext = _extractFileExtension(source);
            var fileName = "media_" + Date.now() + "_" + Utils.generateUniqueId() + "_" + index + "." + ext;
            targetPath = BOT_CONFIG.MEDIA_TEMP_DIR + "/" + fileName;
            var dir = new P.File(BOT_CONFIG.MEDIA_TEMP_DIR); if (!dir.exists()) dir.mkdirs();
            if (_downloadFromUrl(source, targetPath)) { downloaded = true; _scanMedia(targetPath); } else { return null; }
        } else if (isBase64) {
            targetPath = _saveBase64ToFile(source, index);
            if (!targetPath) return null;
            downloaded = true;
        } else {
            targetPath = source;
            if (!new P.File(targetPath).exists()) { Log.e("[FILE] 로컬 파일 없음: " + targetPath); return null; }
            _scanMedia(targetPath);
        }
        var ext = _extractFileExtension(targetPath);
        return { path: targetPath, mimeType: _determineMimeType(ext), downloaded: downloaded };
    }

    function _createSafeFileUri(filePath) {
        try {
            var file = new P.File(filePath);
            if (!file.exists()) { Log.e("[URI] 파일 없음: " + filePath); return null; }
            try {
                return P.FileProvider.getUriForFile(context, BOT_CONFIG.FILE_PROVIDER_AUTHORITY, file);
            } catch (e) {
                return P.Uri.fromFile(file);
            }
        } catch (e) {
            Log.e("[URI] 생성 실패: " + e); return null;
        }
    }

    function _disableStrictMode() {
        try {
            P.StrictMode.setThreadPolicy(P.StrictMode.ThreadPolicy.LAX);
            P.StrictMode.setVmPolicy(P.StrictMode.VmPolicy.LAX);
        } catch (e) {
            try { P.StrictMode.setVmPolicy(new P.StrictMode.VmPolicy.Builder().build()); } catch (e2) {}
        }
    }

    function _buildIntent(channelId, mimeType, uriData, isMultiple) {
        var action = isMultiple ? P.Intent.ACTION_SEND_MULTIPLE : P.Intent.ACTION_SEND;
        var intent = new P.Intent(action);
        intent.setPackage(BOT_CONFIG.KAKAOTALK_PACKAGE_NAME);
        intent.setType(mimeType);
        if (isMultiple) { intent.putParcelableArrayListExtra(P.Intent.EXTRA_STREAM, uriData); } else { intent.putExtra(P.Intent.EXTRA_STREAM, uriData); }
        intent.putExtra("key_id", new P.Long(channelId.toString()));
        intent.putExtra("key_type", new P.Integer(1));
        intent.putExtra("key_from_direct_share", true);
        intent.addFlags(P.Intent.FLAG_ACTIVITY_NEW_TASK | P.Intent.FLAG_ACTIVITY_CLEAR_TOP | P.Intent.FLAG_GRANT_READ_URI_PERMISSION);
        return intent;
    }

    function _calculateWaitTime(filePath) {
        try {
            var file = new P.File(filePath);
            if (!file.exists()) return BOT_CONFIG.FILE_SEND_TIMING.SINGLE_FILE.MIN_WAIT;
            
            var fileSize = file.length();
            var waitTime = BOT_CONFIG.FILE_SEND_TIMING.BASE_WAIT_TIME + 
                          (fileSize / 1048576) * BOT_CONFIG.FILE_SEND_TIMING.SIZE_BASED_WAIT_PER_MB;
            
            return Math.min(Math.max(Math.round(waitTime), BOT_CONFIG.FILE_SEND_TIMING.SINGLE_FILE.MIN_WAIT), 
                          BOT_CONFIG.FILE_SEND_TIMING.SINGLE_FILE.MAX_WAIT);
        } catch (e) { 
            return BOT_CONFIG.FILE_SEND_TIMING.SINGLE_FILE.MIN_WAIT; 
        }
    }

    function _calculateMultiFileWaitTime(processedFiles) {
        try {
            var totalSize = 0;
            var fileCount = processedFiles.length;
            
            for (var i = 0; i < processedFiles.length; i++) {
                var file = new P.File(processedFiles[i].path);
                if (file.exists()) {
                    totalSize += file.length();
                }
            }
            
            var waitTime = BOT_CONFIG.FILE_SEND_TIMING.BASE_WAIT_TIME + 
                          (totalSize / 1048576) * BOT_CONFIG.FILE_SEND_TIMING.SIZE_BASED_WAIT_PER_MB + 
                          (fileCount - 1) * BOT_CONFIG.FILE_SEND_TIMING.COUNT_BASED_WAIT_PER_FILE;
            
            return Math.min(Math.max(Math.round(waitTime), BOT_CONFIG.FILE_SEND_TIMING.MULTI_FILE.MIN_WAIT), 
                          BOT_CONFIG.FILE_SEND_TIMING.MULTI_FILE.MAX_WAIT);
        } catch (e) { 
            return BOT_CONFIG.FILE_SEND_TIMING.MULTI_FILE.MIN_WAIT; 
        }
    }

    function _goHome() {
        try {
            var pm = context.getPackageManager();
            var intent = pm.getLaunchIntentForPackage("com.xfl.msgbot");
            intent.addFlags(P.Intent.FLAG_ACTIVITY_NEW_TASK | P.Intent.FLAG_ACTIVITY_CLEAR_TOP);
            context.startActivity(intent);
            java.lang.Thread.sleep(200);
        } catch (e) {
            Log.e("[goHome] 메신저봇 이동 실패: " + e);
            var homeIntent = new P.Intent(P.Intent.ACTION_MAIN);
            homeIntent.addCategory(P.Intent.CATEGORY_HOME);
            homeIntent.setFlags(P.Intent.FLAG_ACTIVITY_NEW_TASK | P.Intent.FLAG_ACTIVITY_CLEAR_TOP);
            context.startActivity(homeIntent);
        }
    }

    function _cleanupFiles(files) {
        java.lang.Thread.sleep(1000);
        for (var i = 0; i < files.length; i++) {
            if (files[i].downloaded) {
                try {
                    var tempFile = new P.File(files[i].path);
                    if (tempFile.exists()) tempFile.delete();
                } catch (e) { Log.e("[CLEANUP] 임시 파일 삭제 실패: " + e); }
            }
        }
    }

    function send(channelId, sources, serverWaitTime) {
        try {
            _disableStrictMode();
            var sourcesArray = Array.isArray(sources) ? sources : [sources];
            var processedFiles = [];
            for (var i = 0; i < sourcesArray.length; i++) {
                var fileInfo = _prepareFile(sourcesArray[i], i);
                if (fileInfo) processedFiles.push(fileInfo);
            }
            if (processedFiles.length === 0) { Log.e("[MEDIA] 처리할 파일 없음"); return false; }

            var uriList = new P.ArrayList();
            for (var j = 0; j < processedFiles.length; j++) {
                var uri = _createSafeFileUri(processedFiles[j].path);
                if (uri) uriList.add(uri);
            }
            if (uriList.isEmpty()) { Log.e("[MEDIA] 유효한 URI 없음"); return false; }

            var isMultiple = processedFiles.length > 1;
            var mimeType = isMultiple ? "*/*" : processedFiles[0].mimeType;
            var intent = _buildIntent(channelId, mimeType, isMultiple ? uriList : uriList.get(0), isMultiple);
            context.startActivity(intent);

            var waitTime;
            
            // 서버에서 지정한 대기 시간이 있으면 우선 사용
            if (serverWaitTime && typeof serverWaitTime === 'number' && serverWaitTime > 0) {
                waitTime = serverWaitTime;
                if (BOT_CONFIG.LOGGING.CORE_MESSAGES) {
                    Log.i("[MEDIA] 서버 지정 대기시간 사용: " + waitTime + "ms");
                }
            } else {
                // 서버 지정 대기시간이 없으면 클라이언트 기본 로직 사용
                if (isMultiple) {
                    waitTime = _calculateMultiFileWaitTime(processedFiles);
                    if (BOT_CONFIG.LOGGING.CORE_MESSAGES) {
                        Log.i("[MEDIA] 멀티 파일 전송 대기 (클라이언트 계산): " + processedFiles.length + "개 파일, " + waitTime + "ms");
                    }
                } else {
                    waitTime = _calculateWaitTime(processedFiles[0].path);
                    if (BOT_CONFIG.LOGGING.CORE_MESSAGES) {
                        Log.i("[MEDIA] 단일 파일 전송 대기 (클라이언트 계산): " + waitTime + "ms");
                    }
                }
            }
            
            java.lang.Thread.sleep(waitTime);
            _goHome();
            _cleanupFiles(processedFiles);
            return true;
        } catch (e) {
            Log.e("[MEDIA] 전송 실패: " + e); return false;
        }
    }
    
    // v3.3.0: 새로운 패킷 구조 처리
    function handleMediaResponse(data, rawContent) {
        var messageType = data.message_type;
        var roomName = data.room;
        var channelId = data.channel_id;
        var serverWaitTime = data.media_wait_time || null;
        var sources = [];

        // v3.3.0: message_positions를 이용한 멀티 미디어 처리
        if (data.message_positions && data.message_positions.length > 2) {
            // 멀티 미디어 데이터
            var positions = data.message_positions;
            for (var i = 0; i < positions.length - 1; i++) {
                var start = positions[i];
                var end = positions[i + 1];
                sources.push(rawContent.substring(start, end));
            }
            Log.i("[MEDIA] 멀티 " + messageType + " 처리: " + sources.length + "개");
        } else {
            // 단일 미디어 데이터
            sources = [rawContent];
            Log.i("[MEDIA] 단일 " + messageType + " 처리");
        }

        if (!channelId && roomName) {
            channelId = BotCore.findChannelIdByRoomName(roomName);
        }

        if (channelId && sources.length > 0) {
            Log.i("[MEDIA] 미디어 전송 시작: " + sources.length + "개" + 
                  (serverWaitTime ? " (서버 지정 대기시간: " + serverWaitTime + "ms)" : ""));
            send(channelId, sources, serverWaitTime);
            return true;
        } else {
            Log.e("[MEDIA] 전송 실패 - channelId 없음 또는 소스 없음: " + roomName);
            return false;
        }
    }

    return { 
        handleMediaResponse: handleMediaResponse
    };
})();

// =============================================================================
// 6. 봇 핵심 로직 모듈 (BotCore) - v3.3.0 업데이트
// =============================================================================
var BotCore = (function() {
    var bot = BotManager.getCurrentBot();
    var currentServerIndex = 0;
    var socket = null;
    var outputStream = null;
    var receiveThread = null;
    var reconnectTimeout = null;
    var cleanupTimeout = null;
    var isConnected = false;
    var isReconnecting = false;
    var reconnectAttempts = 0;
    var messageQueue = [];
    var isProcessingQueue = false;
    var currentRooms = {};

    // 우선순위 기반 서버 정렬
    function _getSortedServers() {
        var sorted = BOT_CONFIG.SERVER_LIST.slice();
        sorted.sort(function(a, b) { return a.priority - b.priority; });
        return sorted;
    }

    function _safeCloseThread(thread, timeoutMs) {
        if (!thread || !thread.isAlive()) return true;
        
        try {
            thread.interrupt();
            thread.join(timeoutMs || BOT_CONFIG.THREAD_JOIN_TIMEOUT);
            return !thread.isAlive();
        } catch (e) {
            Log.w("[THREAD] 스레드 종료 대기 실패: " + e);
            return false;
        }
    }

    function _closeSocket() {
        isConnected = false;
        if (BOT_CONFIG.LOGGING.CONNECTION_EVENTS) {
            Log.i("[CORE] 소켓 연결 종료 시작");
        }
        try {
            if (receiveThread) {
                var closed = _safeCloseThread(receiveThread, BOT_CONFIG.THREAD_JOIN_TIMEOUT);
                if (!closed) {
                    Log.w("[CORE] 스레드 강제 종료 대기 실패");
                }
                receiveThread = null;
            }
            
            if (outputStream) { 
                outputStream.close(); 
                outputStream = null; 
            }
            
            if (socket && !socket.isClosed()) { 
                socket.close(); 
                socket = null; 
            }
            
            if (BOT_CONFIG.LOGGING.CONNECTION_EVENTS) {
                Log.i("[CORE] 소켓 연결 해제 완료");
            }
        } catch (e) { 
            Log.e("[CORE] 소켓 해제 오류: " + e); 
        }
    }

    function _scheduleReconnect() {
        if (isReconnecting || reconnectTimeout) return;
        isReconnecting = true;
        reconnectAttempts++;
        
        if (BOT_CONFIG.MAX_RECONNECT_ATTEMPTS > 0 && reconnectAttempts > BOT_CONFIG.MAX_RECONNECT_ATTEMPTS) {
            Log.e("[CORE] 최대 재연결 시도 횟수 초과. 중단.");
            isReconnecting = false; 
            return;
        }
        
        var delay = BOT_CONFIG.BASE_RECONNECT_DELAY * Math.pow(2, Math.min(reconnectAttempts, 6));
        delay = Math.min(delay, BOT_CONFIG.MAX_RECONNECT_DELAY);
        
        if (BOT_CONFIG.LOGGING.CONNECTION_EVENTS) {
            Log.i("[CORE] 재연결 예약: " + delay + "ms 후 (시도 " + reconnectAttempts + 
                  (BOT_CONFIG.MAX_RECONNECT_ATTEMPTS === -1 ? "/무한" : ("/" + BOT_CONFIG.MAX_RECONNECT_ATTEMPTS)) + ")");
        }
        
        reconnectTimeout = setTimeout(function() {
            reconnectTimeout = null; 
            isReconnecting = false;
            _attemptConnectionToAllServers();
        }, delay);
    }

    function _cleanupExpiredMessages() {
        var now = Date.now();
        var initialLength = messageQueue.length;
        var expiredCount = 0;
        
        messageQueue = messageQueue.filter(function(queueItem) {
            if (now - queueItem.timestamp > BOT_CONFIG.MESSAGE_TTL) {
                expiredCount++;
                return false;
            }
            return true;
        });
        
        if (expiredCount > 0 && BOT_CONFIG.LOGGING.QUEUE_OPERATIONS) {
            Log.i("[TTL] 만료된 메시지 " + expiredCount + "개 정리됨 (큐 크기: " + initialLength + " → " + messageQueue.length + ")");
        }
    }

    // v3.3.0: JSON + Raw 데이터 전송
    function _sendMessageInternal(packet, rawContent) {
        if (!isConnected) {
            var queueItem = {
                packet: packet,
                rawContent: rawContent,
                timestamp: Date.now()
            };
            messageQueue.push(queueItem);
            if (BOT_CONFIG.LOGGING.QUEUE_OPERATIONS) {
                Log.i("[QUEUE] 메시지 큐에 추가 (TTL: " + BOT_CONFIG.MESSAGE_TTL + "ms): " + packet.event);
            }
            return false;
        }
        try {
            var jsonStr = JSON.stringify(packet);
            var fullPacket = jsonStr + (rawContent || "") + "\n";
            outputStream.write(fullPacket);
            outputStream.flush();
            
            if (BOT_CONFIG.LOGGING.MESSAGE_TRANSFER) {
                if (BOT_CONFIG.LOGGING.MESSAGE_CONTENT_DETAIL) {
                    Log.i("[SEND] " + packet.event + " 메시지 전송 완료 - JSON: " + jsonStr + (rawContent ? " + Raw데이터(" + rawContent.length + "bytes)" : ""));
                } else if (BOT_CONFIG.LOGGING.MESSAGE_CONTENT) {
                    Log.i("[SEND] " + packet.event + " 메시지 전송 완료 - 패킷크기: " + fullPacket.length + "bytes");
                } else {
                    Log.i("[SEND] " + packet.event + " 메시지 전송 완료");
                }
            }
            return true;
        } catch (e) {
            Log.e("[SEND] 메시지 전송 실패: " + e);
            var queueItem = {
                packet: packet,
                rawContent: rawContent,
                timestamp: Date.now()
            };
            messageQueue.unshift(queueItem);
            _closeSocket(); _scheduleReconnect();
            return false;
        }
    }

    function _processMessageQueue() {
        if (isProcessingQueue || !isConnected || messageQueue.length === 0) return;
        isProcessingQueue = true;
        
        _cleanupExpiredMessages();
        
        if (BOT_CONFIG.MAX_QUEUE_SIZE > 0 && messageQueue.length > BOT_CONFIG.MAX_QUEUE_SIZE) {
            var removed = messageQueue.splice(0, messageQueue.length - BOT_CONFIG.MAX_QUEUE_SIZE);
            Log.w("[CLEANUP] 극단적 큐 크기 제한으로 " + removed.length + "개 메시지 제거");
        }
        
        var processedCount = 0;
        var maxProcessPerCycle = 10;
        
        while (messageQueue.length > 0 && isConnected && processedCount < maxProcessPerCycle) {
            var queueItem = messageQueue.shift();
            var now = Date.now();
            
            if (now - queueItem.timestamp > BOT_CONFIG.MESSAGE_TTL) {
                Log.w("[TTL] 만료된 메시지 폐기: " + queueItem.packet.event + " (나이: " + (now - queueItem.timestamp) + "ms)");
                continue;
            }
            
            if (_sendMessageInternal(queueItem.packet, queueItem.rawContent)) {
                processedCount++;
            } else {
                messageQueue.unshift(queueItem);
                break;
            }
        }
        
        if (processedCount > 0 && BOT_CONFIG.LOGGING.QUEUE_OPERATIONS) {
            Log.i("[QUEUE] 큐 처리 완료: " + processedCount + "개 전송, 남은 큐 크기: " + messageQueue.length);
        }
        
        isProcessingQueue = false;
    }

    // v3.3.0: 통합된 서버 응답 처리 (모든 이벤트 JSON+Raw)
    function _handleServerResponse(rawMsg) {
        try {
            // 🟢 단일 파싱 로직 (모든 이벤트 동일)
            var trimmedMsg = rawMsg.trim();
            var jsonEndIndex = trimmedMsg.lastIndexOf('}');
            
            if (jsonEndIndex === -1) {
                Log.e("[RESPONSE] 유효하지 않은 패킷 형식");
                return;
            }
            
            var jsonPart = trimmedMsg.substring(0, jsonEndIndex + 1);
            var packet = JSON.parse(jsonPart);
            var rawData = trimmedMsg.substring(jsonEndIndex + 1);
            
            var event = packet.event;
            var data = packet.data;
            
            if (!data) {
                Log.e("[RESPONSE] 데이터 필드 없음");
                return;
            }
            
            var positions = data.message_positions || [0, 0];
            
            // 통합 로깅
            _logReceivedPacket(event, jsonPart.length, rawData.length);
            
            // 이벤트별 분기 처리
            switch(event) {
                case "handshakeComplete":
                    _handleHandshakeResponse(data);
                    break;
                case "ping":
                    _handlePingResponse(data);
                    break;
                case "messageResponse":
                case "scheduleMessage":
                case "broadcastMessage":
                    _handleMessageResponse(data, rawData, positions);
                    break;
                default:
                    Log.w("[RECV] 알 수 없는 이벤트: " + event);
            }
            
        } catch (e) {
            Log.e("[RESPONSE] 응답 처리 실패: " + e);
        }
    }

    // 통합 패킷 로깅
    function _logReceivedPacket(event, jsonSize, rawSize) {
        if (!BOT_CONFIG.LOGGING.MESSAGE_TRANSFER) return;
        
        if (BOT_CONFIG.LOGGING.MESSAGE_CONTENT_DETAIL) {
            if (rawSize > 0) {
                Log.i("[RECV] " + event + " - JSON: " + jsonSize + "bytes, Raw: " + rawSize + "bytes");
            } else {
                Log.i("[RECV] " + event + " - JSON: " + jsonSize + "bytes");
            }
        } else if (BOT_CONFIG.LOGGING.MESSAGE_CONTENT) {
            Log.i("[RECV] " + event + " - 총 크기: " + (jsonSize + rawSize) + "bytes");
        } else {
            Log.i("[RECV] " + event + " 메시지 수신");
        }
    }

    // Handshake 응답 처리
    function _handleHandshakeResponse(data) {
        Log.i("[HANDSHAKE] 서버 응답: " + (data.success ? "성공" : "실패"));
        if (data.success) {
            Log.i("[HANDSHAKE] 승인 상태: " + (data.approved ? "승인됨" : "대기중") + " - " + data.message);
            Log.i("[HANDSHAKE] 서버 버전: " + data.server_version);
        } else {
            Log.e("[HANDSHAKE] 핸드셰이크 실패");
            _closeSocket();
            _scheduleReconnect();
        }
    }

    // Ping 응답 처리
    function _handlePingResponse(data) {
        var pingData = {
            bot_name: data.bot_name || BOT_CONFIG.BOT_NAME,
            server_timestamp: data.server_timestamp
        };
        
        // 모니터링 데이터 수집
        if (BOT_CONFIG.MONITORING_ENABLED) {
            pingData.monitoring = _collectMonitoringData();
        }
        
        // Auth 데이터 추가
        pingData.auth = Auth.createAuthData();
        
        if (BOT_CONFIG.LOGGING.PING_EVENTS) {
            _logPingData(pingData);
        }
        
        // 🟢 통합된 전송 함수 사용 - pong으로 응답
        _sendV330Message('pong', pingData, "");
    }

    // 메시지 응답 처리
    function _handleMessageResponse(data, rawData, positions) {
        if (positions.length === 2 && positions[1] === 0) {
            // Raw 데이터 없음 (빈 응답)
            return;
        }
        
        var messageType = data.message_type;
        var content = rawData;
        
        // 줄바꿈 제거
        if (content.endsWith('\n')) {
            content = content.substring(0, content.length - 1);
        }
        
        if (messageType === BOT_CONFIG.MESSAGE_TYPES.TEXT) {
            // 텍스트 메시지 처리
            if (data.content_encoding === "base64") {
                content = Utils.base64Decode(content);
            }
            bot.send(data.room, content);
            
            if (BOT_CONFIG.LOGGING.MESSAGE_CONTENT) {
                var preview = content.length > 100 ? content.substring(0, 100) + "..." : content;
                Log.i("[MESSAGE] 텍스트: " + preview);
            }
        } else if ([BOT_CONFIG.MESSAGE_TYPES.IMAGE, BOT_CONFIG.MESSAGE_TYPES.AUDIO, 
                   BOT_CONFIG.MESSAGE_TYPES.VIDEO, BOT_CONFIG.MESSAGE_TYPES.DOCUMENT].indexOf(messageType) !== -1) {
            // 미디어 메시지 처리
            MediaHandler.handleMediaResponse(data, content);
            
            if (BOT_CONFIG.LOGGING.MESSAGE_CONTENT) {
                var mediaCount = positions.length > 2 ? positions.length - 1 : 1;
                Log.i("[MESSAGE] " + messageType + ": " + mediaCount + "개");
            }
        }
    }

    // 모니터링 데이터 수집
    function _collectMonitoringData() {
        try {
            var runtime = java.lang.Runtime.getRuntime();
            var totalMemory = 0, freeMemory = 0, maxMemory = 0;
            
            try {
                totalMemory = runtime.totalMemory() / 1024 / 1024;
                freeMemory = runtime.freeMemory() / 1024 / 1024;
                maxMemory = runtime.maxMemory() / 1024 / 1024;
            } catch (memErr) {
                Log.w("[PING] 메모리 정보 수집 실패: " + memErr);
            }
            
            var usedMemory = totalMemory - freeMemory;
            var memoryPercent = maxMemory > 0 ? (usedMemory / maxMemory) * 100 : 0;
            
            return {
                total_memory: parseFloat(maxMemory.toFixed(1)),
                memory_usage: parseFloat(usedMemory.toFixed(1)),
                memory_percent: parseFloat(memoryPercent.toFixed(1)),
                message_queue_size: messageQueue.length || 0,
                active_rooms: Object.keys(currentRooms).length || 0
            };
        } catch (e) {
            Log.e("[MONITORING] 데이터 수집 실패: " + e);
            return {};
        }
    }

    // Ping 데이터 로깅
    function _logPingData(pingData) {
        if (BOT_CONFIG.LOGGING.MESSAGE_CONTENT_DETAIL && pingData.monitoring) {
            Log.i("[PING] 응답 전송 (모니터링 포함) - 전체: " + JSON.stringify(pingData));
        } else if (BOT_CONFIG.LOGGING.MESSAGE_CONTENT && pingData.monitoring) {
            Log.i("[PING] 응답 전송 (모니터링 포함) - 데이터: " + JSON.stringify(pingData.monitoring));
        } else {
            Log.i("[PING] 응답 전송" + (pingData.monitoring ? " (모니터링 포함)" : ""));
        }
    }

    function _startReceiveThread() {
        if (receiveThread) {
            if (receiveThread.isAlive()) {
                Log.w("[THREAD] 기존 스레드가 살아있음, 안전 종료 시도");
                _safeCloseThread(receiveThread, 3000);
            }
            receiveThread = null;
        }
        
        receiveThread = new java.lang.Thread(function() {
            var inputStream = null;
            try {
                inputStream = new java.io.BufferedReader(new java.io.InputStreamReader(socket.getInputStream(), "UTF-8"));
                Log.i("[THREAD] 수신 스레드 시작: " + java.lang.Thread.currentThread().getName());
                
                while (!java.lang.Thread.interrupted() && socket && !socket.isClosed()) {
                    var line = inputStream.readLine();
                    if (line === null) throw "서버 연결 종료";
                    _handleServerResponse(line);
                }
            } catch (err) {
                if (!java.lang.Thread.interrupted()) {
                    Log.e("[RECEIVE] 수신 스레드 오류: " + err);
                    _closeSocket(); 
                    _scheduleReconnect();
                }
            } finally {
                if (inputStream) try { inputStream.close(); } catch (e) {}
                Log.i("[THREAD] 수신 스레드 종료: " + java.lang.Thread.currentThread().getName());
            }
        });
        receiveThread.start();
    }

    // 🔴 강화된 핸드셰이크 시스템
    function _connectToSingleServer(serverInfo) {
        try {
            Log.i("[CONNECT] 연결 시도: " + serverInfo.name + " (우선순위: " + serverInfo.priority + ")");
            var address = new java.net.InetSocketAddress(serverInfo.host, serverInfo.port);
            socket = new java.net.Socket();
            socket.connect(address, 5000);
            socket.setSoTimeout(0);
            outputStream = new java.io.BufferedWriter(new java.io.OutputStreamWriter(socket.getOutputStream(), "UTF-8"));
            
            // 🔴 DeviceInfo와 Auth 모듈에 소켓 참조 전달
            DeviceInfo.setSocket(socket);
            Auth.setSocket(socket);
            
            // 🟢 v3.3.0: JSON+Raw 구조로 핸드셰이크 전송
            var handshakeData = DeviceInfo.createHandshakeData();
            
            if (BOT_CONFIG.LOGGING.CONNECTION_EVENTS) {
                Log.i("[HANDSHAKE] 전송: " + JSON.stringify(handshakeData));
            }
            
            // v3.3.0 프로토콜로 전송 (Raw 데이터 없음)
            _sendV330Message('handshake', handshakeData, "");
            isConnected = true; reconnectAttempts = 0;
            
            if (BOT_CONFIG.LOGGING.CONNECTION_EVENTS) {
                Log.i("[CONNECT] 연결 성공: " + serverInfo.name);
            }
            _startReceiveThread(); 
            _processMessageQueue();
            return true;
        } catch (e) {
            Log.e("[CONNECT] 연결 실패: " + serverInfo.name + " - " + e);
            _closeSocket();
            return false;
        }
    }

    function _attemptConnectionToAllServers() {
        var sortedServers = _getSortedServers();
        var originalIndex = currentServerIndex;
        
        for (var i = 0; i < sortedServers.length; i++) {
            var serverInfo = sortedServers[currentServerIndex];
            if (_connectToSingleServer(serverInfo)) return true;
            currentServerIndex = (currentServerIndex + 1) % sortedServers.length;
            
            if (currentServerIndex === originalIndex) break;
        }
        
        Log.e("[CONNECT] 모든 서버 연결 실패. 재시도 예약.");
        _scheduleReconnect();
        return false;
    }

    function onMessage(msg) {
        if (msg.channelId) {
            var channelIdStr = msg.channelId.toString();
            currentRooms[channelIdStr] = {
                room: msg.room,
                lastActivity: Date.now()
            };
        }
        var sanitizedContent = Utils.sanitizeText(msg.content);
        if (!sanitizedContent) return;
        
        if (BOT_CONFIG.LOGGING.CORE_MESSAGES) {
            if (BOT_CONFIG.LOGGING.MESSAGE_CONTENT_DETAIL) {
                Log.i("[MSG] 메시지 처리: " + msg.room + " / " + msg.author.name + " - 전체내용: " + sanitizedContent);
            } else if (BOT_CONFIG.LOGGING.MESSAGE_CONTENT) {
                var contentPreviewLength = Math.min(100, sanitizedContent.length);
                Log.i("[MSG] 메시지 처리: " + msg.room + " / " + msg.author.name + " - 내용: " + sanitizedContent.substring(0, contentPreviewLength) + (sanitizedContent.length > contentPreviewLength ? "..." : ""));
            } else {
                Log.i("[MSG] 메시지 처리: " + msg.room + " / " + msg.author.name);
            }
        }
        
        // v3.3.0: message 이벤트로 변경, Base64 인코딩
        var messageData = {
            room: msg.room,
            channel_id: msg.channelId ? msg.channelId.toString() : null,
            message_type: BOT_CONFIG.MESSAGE_TYPES.TEXT,
            content_encoding: "base64",
            message_positions: [0, 0], // 클라이언트는 정확한 길이 계산 어려움
            timestamp: Utils.formatTimestamp(new Date()),
            timezone: "Asia/Seoul",
            
            // 기존 호환성 필드
            sender: Utils.sanitizeText(msg.author.name),
            is_group_chat: msg.isGroupChat,
            log_id: msg.logId ? msg.logId.toString() : null,
            user_hash: msg.author.hash || null,
            is_mention: !!msg.isMention,
            bot_name: BOT_CONFIG.BOT_NAME,
            client_type: BOT_CONFIG.CLIENT_TYPE
        };
        
        // Base64 인코딩된 메시지 내용
        var encodedContent = Utils.base64Encode(sanitizedContent);
        sendMessage('message', messageData, encodedContent);
    }

    // v3.3.0: 통합된 메시지 전송 함수
    function _sendV330Message(event, data, rawContent) {
        // 공통 필드 자동 추가
        data.timestamp = Utils.formatTimestamp(new Date());
        data.timezone = "Asia/Seoul";
        
        // message_positions 자동 계산
        if (rawContent && rawContent.length > 0) {
            var contentBytes = rawContent.length; // JavaScript에서는 UTF-8 바이트 계산 근사치
            data.message_positions = [0, contentBytes];
        } else {
            data.message_positions = [0, 0];
        }
        
        var packet = { event: event, data: data };
        return _sendMessageInternal(packet, rawContent || "");
    }

    function sendMessage(event, data, rawContent) {
        // Auth 데이터 자동 추가 (ping, handshake 등은 별도 처리)
        if (event !== 'handshake' && !data.auth) {
            data.auth = Auth.createAuthData();
        }
        
        return _sendV330Message(event, data, rawContent || "");
    }

    function findChannelIdByRoomName(roomName) {
        for (var cid in currentRooms) {
            if (currentRooms[cid].room === roomName) return cid;
        }
        return null;
    }

    function updateRoomInfo(channelId, roomName) {
        currentRooms[channelId] = {
            room: roomName,
            lastActivity: Date.now()
        };
    }

    function _performPeriodicCleanup() {
        var now = Date.now();
        var dayMs = 24 * 60 * 60 * 1000;
        
        Log.i("[CLEANUP] 주기적 정리 작업 시작");
        
        var inactiveCutoff = now - (BOT_CONFIG.ROOM_INACTIVE_DAYS * dayMs);
        var removedRooms = 0;
        for (var channelId in currentRooms) {
            if (currentRooms[channelId].lastActivity < inactiveCutoff) {
                delete currentRooms[channelId];
                removedRooms++;
            }
        }
        if (removedRooms > 0) {
            Log.i("[CLEANUP] " + removedRooms + "개 비활성 방 정리됨 (" + BOT_CONFIG.ROOM_INACTIVE_DAYS + "일+ 기준)");
        }
        
        _cleanupOldTempFiles(BOT_CONFIG.TEMP_FILE_MAX_AGE_DAYS);
        
        if (BOT_CONFIG.MAX_QUEUE_SIZE > 0 && messageQueue.length > BOT_CONFIG.MAX_QUEUE_SIZE) {
            var removed = messageQueue.splice(0, messageQueue.length - BOT_CONFIG.MAX_QUEUE_SIZE);
            Log.w("[CLEANUP] 극단적 큐 크기 제한으로 " + removed.length + "개 메시지 제거");
        }
        
        Log.i("[CLEANUP] 주기적 정리 작업 완료");
    }

    function _cleanupOldTempFiles(maxAgeDays) {
        try {
            var mediaDir = new BOT_CONFIG.PACKAGES.File(BOT_CONFIG.MEDIA_TEMP_DIR);
            if (!mediaDir.exists()) return;
            
            var cutoffTime = Date.now() - (maxAgeDays * 24 * 60 * 60 * 1000);
            var files = mediaDir.listFiles();
            var deletedCount = 0;
            
            for (var i = 0; files && i < files.length; i++) {
                if (files[i].lastModified() < cutoffTime) {
                    if (files[i].delete()) deletedCount++;
                }
            }
            
            if (deletedCount > 0) {
                Log.i("[CLEANUP] " + deletedCount + "개 오래된 임시 파일 정리됨 (" + maxAgeDays + "일+ 기준)");
            }
        } catch (e) {
            Log.e("[CLEANUP] 임시 파일 정리 실패: " + e);
        }
    }

    function _schedulePeriodicTasks() {
        cleanupTimeout = setInterval(function() {
            _performPeriodicCleanup();
        }, BOT_CONFIG.CLEANUP_INTERVAL);
                
        Log.i("[SCHEDULE] 정기 작업 스케줄링 완료 (정리: " + (BOT_CONFIG.CLEANUP_INTERVAL/3600000) + "시간, 모니터링: PING 기반)");
    }

    function cleanup() {
        Log.i("[CORE] 정리 시작");
        
        if (reconnectTimeout) {
            clearTimeout(reconnectTimeout);
            reconnectTimeout = null;
        }
        if (cleanupTimeout) {
            clearInterval(cleanupTimeout);
            cleanupTimeout = null;
        }
        
        _closeSocket();
        
        Log.i("[CORE] 정리 완료");
    }

    function initializeEventListeners() {
        Log.i("[CORE] 이벤트 리스너 초기화 시작");
        bot.addListener(Event.MESSAGE, onMessage);
        bot.addListener(Event.START_COMPILE, function() {
            Log.i("[CORE] 컴파일 시작, 리소스 정리");
            cleanup();
        });
        
        _schedulePeriodicTasks();
        
        setTimeout(function() {
            Log.i("[CORE] 지연된 서버 연결 시작");
            _attemptConnectionToAllServers();
        }, BOT_CONFIG.INITIALIZATION_DELAY);
    }

    function start() {
        Log.i("[CORE] 봇 시작 (버전: " + BOT_CONFIG.VERSION + ", 프로토콜: " + BOT_CONFIG.PROTOCOL_VERSION + ")");
        Log.i("[CORE] v3.3.0: JSON + Raw 데이터 구조, Base64 인코딩, UTC 타임스탬프");
        Log.i("[CORE] 우선순위 기반 서버 순서: " + _getSortedServers().map(function(s) { return s.name + "(P" + s.priority + ")"; }).join(", "));
        Log.i("[CORE] 재연결 설정: " + (BOT_CONFIG.MAX_RECONNECT_ATTEMPTS === -1 ? "무한" : BOT_CONFIG.MAX_RECONNECT_ATTEMPTS + "회"));
        Log.i("[CORE] 강화된 핸드셰이크 시스템 활성화");
        initializeEventListeners();
    }

    return { 
        start: start, 
        findChannelIdByRoomName: findChannelIdByRoomName,
        updateRoomInfo: updateRoomInfo,
        cleanup: cleanup,
        initializeEventListeners: initializeEventListeners
    };
})();

// =============================================================================
// 7. 메인 모듈 (MainModule)
// =============================================================================
var MainModule = (function() {
    function initializeEventListeners() {
        Log.i("[MAIN] initializeEventListeners 호출 - v3.3.0 프로토콜 적용");
        BotCore.initializeEventListeners();
    }

    return {
        initializeEventListeners: initializeEventListeners
    };
})();

// =============================================================================
// 8. MessengerBotR 표준 이벤트 핸들러
// =============================================================================

var bot = BotManager.getCurrentBot();

function onStartCompile() {
    Log.i("[SYSTEM] 컴파일 시작 - MessengerBotR Bridge v" + BOT_CONFIG.VERSION + " (프로토콜 v" + BOT_CONFIG.PROTOCOL_VERSION + ")");
}

function onNotificationPosted(sbn, sm) {
    // 알림 처리 로직 (필요한 경우 구현)
}

function response(room, msg, sender, isGroupChat, replier, imageDB, packageName, threadId) {
    try {
        var channelId = imageDB && imageDB.getLastUid ? imageDB.getLastUid() : null;
        
        var messageData = {
            room: room,
            text: Utils.sanitizeText(msg),
            sender: Utils.sanitizeText(sender),
            isGroupChat: isGroupChat,
            channelId: channelId ? channelId.toString() : null,
            timestamp: Utils.formatTimestamp(new Date()),
            botName: BOT_CONFIG.BOT_NAME,
            packageName: packageName,
            threadId: threadId,
            userHash: sender ? Utils.generateUniqueId() : null,
            isMention: false
        };
        
        BotCore.sendAnalyzeMessage(messageData);
        
        if (msg.startsWith("MEDIA_") && msg.includes("|||")) {
            var parts = msg.split("|||");
            if (parts.length >= 2) {
                var mediaType = parts[0];
                Log.i("[MEDIA_RECEIVED] " + room + ": " + mediaType);
            }
        }
        
    } catch (e) {
        Log.e("[RESPONSE] 메시지 처리 실패: " + e);
    }
}

function onCreate(savedInstanceState, activity) {
    Log.i("[SYSTEM] MessengerBotR Bridge 생성 완료");
}

function onResume(activity) {
    setTimeout(function() {
        Log.i("[SYSTEM] 연결 초기화 시작");
    }, BOT_CONFIG.INITIALIZATION_DELAY); 
}

function onPause(activity) {
    Log.i("[SYSTEM] 일시정지");
}

function onStop(activity) {
    Log.i("[SYSTEM] 정지");
}

function onRestart(activity) {
    Log.i("[SYSTEM] 재시작");
}

function onDestroy(activity) {
    Log.i("[SYSTEM] 소멸");
    BotCore.cleanup();
}

function onBackPressed(activity) {
    return false;
}

// =============================================================================
// 봇 실행
// =============================================================================
Log.i("[SYSTEM] MessengerBotR Bridge v" + BOT_CONFIG.VERSION + " (프로토콜 v" + BOT_CONFIG.PROTOCOL_VERSION + ") 로드 완료");
Log.i("[CONFIG] 봇 이름: " + BOT_CONFIG.BOT_NAME);
Log.i("[CONFIG] 클라이언트 타입: " + BOT_CONFIG.CLIENT_TYPE);
Log.i("[CONFIG] 서버 수: " + BOT_CONFIG.SERVER_LIST.length);
Log.i("[CONFIG] v3.3.0 주요 개선사항: JSON+Raw 구조, Base64 인코딩, UTC 타임스탬프");
Log.i("[CONFIG] 강화된 핸드셰이크: clientType, botName, version, deviceID, deviceIP, deviceInfo");

MainModule.initializeEventListeners();