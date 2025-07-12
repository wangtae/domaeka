/**
 * MessengerBotR 클라이언트 - 카카오톡 브릿지 스크립트 v3.1.2
 * 
 * @description
 * 카카오톡과 서버 간의 통신을 중개하는 브릿지 클라이언트 스크립트입니다.
 * Connection reset by peer 문제 해결을 위한 연결 안정성이 개선된 버전입니다.
 * 
 * @compatibility MessengerBotR v0.7.38a ~ v0.7.39a
 * @engine Rhino JavaScript Engine
 * 
 * @requirements
 * [필수 권한]
 * • 메신저봇R 처음 설치시 요구하는 권한들 모두 허요.
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
 * @version 3.1.4
 * @author kkobot.com
 * @improvements 연결 안정성 개선, 모듈화, 멀티 서버 지원, 고급 미디어 전송
 */

// =============================================================================
// 1. 설정 모듈 (BOT_CONFIG) - 장기 실행 안정성 강화
// =============================================================================
var BOT_CONFIG = {
    // 기본 정보
    VERSION: '3.1.4',
    BOT_NAME: 'LOA.i',

    // 서버 및 인증 정보
    SECRET_KEY: "8vQw!@#4kLz9^&*1pXyZ2$%6sDq7!@#8vQw!@#4kLz9^&*1pXyZ2$%6sDq7!@#8",
    BOT_SPECIFIC_SALT: "7f$kLz9^&*1pXyZ2",
    SERVER_LIST: [
        { host: "100.69.44.56", port: 1485, priority: 2, name: "Dev.PC 2" },
        { host: "100.73.137.47", port: 1485, priority: 3, name: "Dev.Laptop 1" },
        { host: "100.73.137.47", port: 1486, priority: 4, name: "Dev.Laptop 2" },
        { host: "100.69.44.56", port: 1486, priority: 5, name: "Dev.PC 1" }
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
    // RESOURCE_LOG_INTERVAL: 60000, //3600000,     // 1시간마다 리소스 로그 (PING 모니터링으로 대체)

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
    // =====================================================
    // 파일 전송 후 홈으로 돌아가기 전 대기시간 설정
    // 
    // 📝 설정 가이드:
    // - 대기시간이 너무 짧으면: 파일 전송이 완료되기 전에 홈으로 돌아가서 전송 실패
    // - 대기시간이 너무 길면: 사용자 경험 저하 (불필요한 대기)
    // 
    // 🔧 조절 방법:
    // 1. 작은 파일들이 자주 실패하면 BASE_WAIT_TIME 증가
    // 2. 큰 파일들이 자주 실패하면 SIZE_BASED_WAIT_PER_MB 증가
    // 3. 멀티 파일 전송이 자주 실패하면 COUNT_BASED_WAIT_PER_FILE 증가
    // 4. 전체적으로 너무 빠르면 MIN_WAIT 증가, 너무 느리면 MAX_WAIT 감소
    // =====================================================
    FILE_SEND_TIMING: {
        // 기본 대기시간 (ms) - 모든 파일 전송에 공통으로 적용되는 최소 대기시간
        BASE_WAIT_TIME: 1500,
        
        // 용량 기반 대기시간 (MB당 추가 ms) - 파일 크기에 따른 추가 대기시간
        // 예: 5MB 파일 = 5 × 2000 = 10000ms(10초) 추가 대기
        SIZE_BASED_WAIT_PER_MB: 2000,
        
        // 파일 개수 기반 대기시간 (파일 개수-1 당 추가 ms) - 멀티 파일 전송시 추가 대기
        // 예: 3개 파일 = (3-1) × 500 = 1000ms(1초) 추가 대기
        COUNT_BASED_WAIT_PER_FILE: 300,
        
        // 단일 파일 대기시간 범위
        SINGLE_FILE: {
            MIN_WAIT: 4000,    // 최소 대기시간 (4초) - 아무리 작은 파일도 최소 이 시간은 대기
            MAX_WAIT: 6000     // 최대 대기시간 (6초) - 아무리 큰 파일도 이 시간을 초과하지 않음
        },
        
        // 멀티 파일 대기시간 범위
        MULTI_FILE: {
            MIN_WAIT: 3000,    // 최소 대기시간 (3초) - 멀티 파일 전송시 최소 대기시간
            MAX_WAIT: 15000    // 최대 대기시간 (15초) - 멀티 파일 전송시 최대 대기시간
        }
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
// 2. 유틸리티 모듈 (Utils)
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

    function formatTimestamp(dateObj) {
        if (!(dateObj instanceof Date)) return '';
        var yyyy = dateObj.getFullYear();
        var mm = ('0' + (dateObj.getMonth() + 1)).slice(-2);
        var dd = ('0' + dateObj.getDate()).slice(-2);
        var hh = ('0' + dateObj.getHours()).slice(-2);
        var mi = ('0' + dateObj.getMinutes()).slice(-2);
        var ss = ('0' + dateObj.getSeconds()).slice(-2);
        return yyyy + "-" + mm + "-" + dd + " " + hh + ":" + mi + ":" + ss;
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

    return {
        generateUniqueId: generateUniqueId,
        formatTimestamp: formatTimestamp,
        sanitizeText: sanitizeText
    };
})();

// =============================================================================
// 3. 인증 모듈 (Auth)
// =============================================================================
var Auth = (function() {
    var socketRef; // BotCore에서 설정할 소켓 참조

    // 🔴 Android ID 가져오기 (check-device.js에서 확인된 방식)
    function _getAndroidId() {
        try {
            return android.provider.Settings.Secure.getString(
                android.app.ActivityThread.currentApplication().getContentResolver(),
                android.provider.Settings.Secure.ANDROID_ID
            );
        } catch (e) {
            Log.e("[AUTH] Android ID 가져오기 실패: " + e);
            return "unknown";
        }
    }

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
            clientType: "MessengerBotR",
            botName: BOT_CONFIG.BOT_NAME,
            deviceUUID: _getDeviceUUID(),
            deviceID: _getAndroidId(),  // 🔴 Android ID 추가
            macAddress: _getMacAddress(),
            ipAddress: _getLocalIP(),
            timestamp: Date.now(),
            version: BOT_CONFIG.VERSION
        };
        var signString = ["MessengerBotR", auth.botName, auth.deviceUUID, auth.macAddress, auth.ipAddress, auth.timestamp, BOT_CONFIG.BOT_SPECIFIC_SALT].join('|');
        auth.signature = _generateHMAC(signString, BOT_CONFIG.SECRET_KEY);
        return auth;
    }

    function setSocket(socket) {
        socketRef = socket;
    }

    // 🔴 Android ID 가져오기 함수 외부 노출
    function getAndroidId() {
        return _getAndroidId();
    }

    return { 
        createAuthData: createAuthData, 
        setSocket: setSocket,
        getAndroidId: getAndroidId  // 🔴 추가
    };
})();

// =============================================================================
// 4. 미디어 핸들러 모듈 (MediaHandler)
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
        }
 finally {
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
            downloaded = true; // Base64는 임시 파일이므로 삭제 대상
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

    // 🔴 멀티 파일용 대기 시간 계산 함수 (Config 값 사용)
    function _calculateMultiFileWaitTime(processedFiles) {
        try {
            var totalSize = 0;
            var fileCount = processedFiles.length;
            
            // 모든 파일의 용량 합산
            for (var i = 0; i < processedFiles.length; i++) {
                var file = new P.File(processedFiles[i].path);
                if (file.exists()) {
                    totalSize += file.length();
                }
            }
            
            // 🔴 Config 값으로 대기시간 계산
            var waitTime = BOT_CONFIG.FILE_SEND_TIMING.BASE_WAIT_TIME + 
                          (totalSize / 1048576) * BOT_CONFIG.FILE_SEND_TIMING.SIZE_BASED_WAIT_PER_MB + 
                          (fileCount - 1) * BOT_CONFIG.FILE_SEND_TIMING.COUNT_BASED_WAIT_PER_FILE;
            
            // 🔴 멀티 파일 최소/최대 대기시간 적용
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
        java.lang.Thread.sleep(1000); // 삭제 전 약간의 대기
        for (var i = 0; i < files.length; i++) {
            if (files[i].downloaded) { // downloaded 플래그가 true인 파일만 삭제
                try {
                    var tempFile = new P.File(files[i].path);
                    if (tempFile.exists()) tempFile.delete();
                } catch (e) { Log.e("[CLEANUP] 임시 파일 삭제 실패: " + e); }
            }
        }
    }

    function send(channelId, sources) {
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

            // 🔴 멀티 파일 용량 계산 개선
            var waitTime;
            if (isMultiple) {
                waitTime = _calculateMultiFileWaitTime(processedFiles);
                if (BOT_CONFIG.LOGGING.CORE_MESSAGES) {
                    Log.i("[MEDIA] 멀티 파일 전송 대기: " + processedFiles.length + "개 파일, " + waitTime + "ms");
                }
            } else {
                waitTime = _calculateWaitTime(processedFiles[0].path);
                if (BOT_CONFIG.LOGGING.CORE_MESSAGES) {
                    Log.i("[MEDIA] 단일 파일 전송 대기: " + waitTime + "ms");
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
    
    function handleMediaResponse(data) {
        var messageText = data.text;
        var roomName = data.room;
        var channelId = data.channel_id;
        var sources = [];

        if (messageText.startsWith("MEDIA_URL:")) {
            sources = messageText.substring(10).split("|||");
        } else if (messageText.startsWith("IMAGE_BASE64:")) {
            sources = messageText.substring(13).split("|||");
        } else {
            return false; // 미디어 메시지 아님
        }

        if (!channelId && roomName) {
            channelId = BotCore.findChannelIdByRoomName(roomName);
        }

        if (channelId) {
            Log.i("[MEDIA] 미디어 전송 시작: " + sources.length + "개");
            send(channelId, sources);
        } else {
            Log.e("[MEDIA] 전송 실패 - channelId 없음: " + roomName);
        }
        return true; // 미디어 메시지 처리 완료
    }

    return { handleMediaResponse: handleMediaResponse };
})();

// =============================================================================
// 5. 봇 핵심 로직 모듈 (BotCore) - 장기 실행 안정성 강화
// =============================================================================
var BotCore = (function() {
    var bot = BotManager.getCurrentBot();
    var currentServerIndex = 0;
    var socket = null;
    var outputStream = null;
    var receiveThread = null;
    var reconnectTimeout = null;
    var cleanupTimeout = null;      // 🔴 주기적 정리 타이머
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

    // 🔴 개선된 스레드 안전 종료
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

    // 🔴 개선된 소켓 종료
    function _closeSocket() {
        isConnected = false;
        if (BOT_CONFIG.LOGGING.CONNECTION_EVENTS) {
            Log.i("[CORE] 소켓 연결 종료 시작");
        }
        try {
            // 1. 스레드 안전 종료
            if (receiveThread) {
                var closed = _safeCloseThread(receiveThread, BOT_CONFIG.THREAD_JOIN_TIMEOUT);
                if (!closed) {
                    Log.w("[CORE] 스레드 강제 종료 대기 실패");
                }
                receiveThread = null;
            }
            
            // 2. 스트림 종료
            if (outputStream) { 
                outputStream.close(); 
                outputStream = null; 
            }
            
            // 3. 소켓 종료
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

    // 🔴 무한 재연결로 변경
    function _scheduleReconnect() {
        if (isReconnecting || reconnectTimeout) return;
        isReconnecting = true;
        reconnectAttempts++;
        
        // 🔴 MAX_RECONNECT_ATTEMPTS가 -1이면 무한 재연결
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

    // TTL 기능: 만료된 메시지 정리 (메시지 처리 시점에서만 실행)
    function _cleanupExpiredMessages() {
        var now = Date.now();
        var initialLength = messageQueue.length;
        var expiredCount = 0;
        
        // 만료되지 않은 메시지만 유지
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

    function _sendMessageInternal(packet) {
        if (!isConnected) {
            // TTL 기능: 타임스탬프 추가
            var queueItem = {
                packet: packet,
                timestamp: Date.now()
            };
            messageQueue.push(queueItem);
            if (BOT_CONFIG.LOGGING.QUEUE_OPERATIONS) {
                Log.i("[QUEUE] 메시지 큐에 추가 (TTL: " + BOT_CONFIG.MESSAGE_TTL + "ms): " + packet.event);
            }
            return false;
        }
        try {
            var jsonStr = JSON.stringify(packet) + "\n";
            outputStream.write(jsonStr);
            outputStream.flush();
            
            // 중요 메시지 전송 로깅
            if (BOT_CONFIG.LOGGING.MESSAGE_TRANSFER) {
                if (BOT_CONFIG.LOGGING.MESSAGE_CONTENT_DETAIL) {
                    Log.i("[SEND] " + packet.event + " 메시지 전송 완료 - 전체내용: " + jsonStr);
                } else if (BOT_CONFIG.LOGGING.MESSAGE_CONTENT) {
                    Log.i("[SEND] " + packet.event + " 메시지 전송 완료 - 내용: " + jsonStr.substring(0, 200) + (jsonStr.length > 200 ? "..." : ""));
                } else {
                    Log.i("[SEND] " + packet.event + " 메시지 전송 완료");
                }
            }
            return true;
        } catch (e) {
            Log.e("[SEND] 메시지 전송 실패: " + e);
            // TTL 기능: 실패한 메시지도 타임스탬프와 함께 큐에 추가
            var queueItem = {
                packet: packet,
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
        
        // TTL 기능: 큐 처리 전 만료된 메시지 정리
        _cleanupExpiredMessages();
        
        // 🔴 극단적 상황 대비 큐 크기 체크
        if (BOT_CONFIG.MAX_QUEUE_SIZE > 0 && messageQueue.length > BOT_CONFIG.MAX_QUEUE_SIZE) {
            var removed = messageQueue.splice(0, messageQueue.length - BOT_CONFIG.MAX_QUEUE_SIZE);
            Log.w("[CLEANUP] 극단적 큐 크기 제한으로 " + removed.length + "개 메시지 제거");
        }
        
        var processedCount = 0;
        var maxProcessPerCycle = 10; // 한 번에 최대 10개 처리
        
        while (messageQueue.length > 0 && isConnected && processedCount < maxProcessPerCycle) {
            var queueItem = messageQueue.shift();
            var now = Date.now();
            
            // TTL 체크: 만료된 메시지는 폐기
            if (now - queueItem.timestamp > BOT_CONFIG.MESSAGE_TTL) {
                Log.w("[TTL] 만료된 메시지 폐기: " + queueItem.packet.event + " (나이: " + (now - queueItem.timestamp) + "ms)");
                continue;
            }
            
            // 유효한 메시지 전송
            if (_sendMessageInternal(queueItem.packet)) {
                processedCount++;
            } else {
                // 전송 실패 시 다시 큐에 추가 (타임스탬프 유지)
                messageQueue.unshift(queueItem);
                break;
            }
        }
        
        if (processedCount > 0 && BOT_CONFIG.LOGGING.QUEUE_OPERATIONS) {
            Log.i("[QUEUE] 큐 처리 완료: " + processedCount + "개 전송, 남은 큐 크기: " + messageQueue.length);
        }
        
        isProcessingQueue = false;
    }

    function _handleServerResponse(rawMsg) {
        try {
            var packet = JSON.parse(rawMsg);
            var event = packet.event, data = packet.data;
            if (!data) { Log.e("[RESPONSE] 데이터 없음"); return; }
            
            // 중요 메시지 수신 로깅
            if (BOT_CONFIG.LOGGING.MESSAGE_TRANSFER) {
                if (BOT_CONFIG.LOGGING.MESSAGE_CONTENT_DETAIL) {
                    Log.i("[RECV] " + event + " 메시지 수신 - 전체내용: " + rawMsg);
                } else if (BOT_CONFIG.LOGGING.MESSAGE_CONTENT) {
                    Log.i("[RECV] " + event + " 메시지 수신 - 내용: " + rawMsg.substring(0, 200) + (rawMsg.length > 200 ? "..." : ""));
                } else {
                    Log.i("[RECV] " + event + " 메시지 수신");
                }
            }

            if (event === 'messageResponse') {
                if (MediaHandler.handleMediaResponse(data)) {
                    return; // 미디어 핸들러가 처리했으면 종료
                }
                // 일반 텍스트 메시지
                bot.send(data.room, data.text);
            } else if (event === 'ping') {
                // 🔴 ping 응답 (모니터링 데이터 포함 여부에 따라 단일 응답)
                var pingData = {
                    bot_name: data.bot_name,
                    server_timestamp: data.server_timestamp
                };
                
                // 모니터링 데이터 수집 및 ping 응답에 포함
                if (BOT_CONFIG.MONITORING_ENABLED) {
                    try {
                        // 안전한 메모리 정보 수집
                        var runtime = java.lang.Runtime.getRuntime();
                        var totalMemory = 0, freeMemory = 0, maxMemory = 0;
                        
                        try {
                            totalMemory = runtime.totalMemory() / 1024 / 1024;  // MB
                            freeMemory = runtime.freeMemory() / 1024 / 1024;    // MB
                            maxMemory = runtime.maxMemory() / 1024 / 1024;      // MB
                        } catch (memErr) {
                            Log.w("[PING] 메모리 정보 수집 실패: " + memErr);
                        }
                        
                        var usedMemory = totalMemory - freeMemory;              // MB
                        var memoryPercent = maxMemory > 0 ? (usedMemory / maxMemory) * 100 : 0;
                        
                        var monitoringData = {
                            total_memory: parseFloat(maxMemory.toFixed(1)),
                            memory_usage: parseFloat(usedMemory.toFixed(1)),
                            memory_percent: parseFloat(memoryPercent.toFixed(1)),
                            message_queue_size: messageQueue.length || 0,
                            active_rooms: Object.keys(currentRooms).length || 0
                        };
                        
                        // 모니터링 데이터를 ping 응답에 포함
                        pingData.monitoring = monitoringData;
                        
                    } catch (e) {
                        Log.e("[PING] 모니터링 데이터 수집 실패: " + e);
                        // 모니터링 실패해도 기본 ping 응답은 전송
                    }
                }
                
                // 단일 ping 응답 전송
                if (BOT_CONFIG.LOGGING.PING_EVENTS) {
                    if (BOT_CONFIG.LOGGING.MESSAGE_CONTENT_DETAIL && pingData.monitoring) {
                        Log.i("[PING] ping 응답 전송 (모니터링 데이터 포함) - 전체내용: " + JSON.stringify(pingData));
                    } else if (BOT_CONFIG.LOGGING.MESSAGE_CONTENT && pingData.monitoring) {
                        Log.i("[PING] ping 응답 전송 (모니터링 데이터 포함) - 내용: " + JSON.stringify(pingData.monitoring));
                    } else {
                        Log.i("[PING] ping 응답 전송" + (pingData.monitoring ? " (모니터링 데이터 포함)" : ""));
                    }
                }
                sendMessage('ping', pingData);
                
                return; // ping 처리 완료
            }
        } catch (e) { Log.e("[RESPONSE] 응답 처리 실패: " + e); }
    }

    // 🔴 개선된 스레드 생성 (중복 방지)
    function _startReceiveThread() {
        // 기존 스레드가 있으면 안전하게 종료
        if (receiveThread) {
            if (receiveThread.isAlive()) {
                Log.w("[THREAD] 기존 스레드가 살아있음, 안전 종료 시도");
                _safeCloseThread(receiveThread, 3000); // 3초 대기
            }
            receiveThread = null;
        }
        
        // 새 스레드 생성
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

    function _connectToSingleServer(serverInfo) {
        try {
            Log.i("[CONNECT] 연결 시도: " + serverInfo.name + " (우선순위: " + serverInfo.priority + ")");
            var address = new java.net.InetSocketAddress(serverInfo.host, serverInfo.port);
            socket = new java.net.Socket();
            socket.connect(address, 5000);
            socket.setSoTimeout(0);
            outputStream = new java.io.BufferedWriter(new java.io.OutputStreamWriter(socket.getOutputStream(), "UTF-8"));
            Auth.setSocket(socket); // 인증 모듈에 소켓 참조 전달
            var handshake = { botName: BOT_CONFIG.BOT_NAME, version: BOT_CONFIG.VERSION, deviceID: Auth.getAndroidId() };
            outputStream.write(JSON.stringify(handshake) + "\n");
            outputStream.flush();
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
            
            // 한 바퀴 돌았으면 중단
            if (currentServerIndex === originalIndex) break;
        }
        
        Log.e("[CONNECT] 모든 서버 연결 실패. 재시도 예약.");
        _scheduleReconnect(); // 모든 서버 실패 시 다시 스케줄링
        return false;
    }

    // 🔴 방 정보 업데이트 (lastActivity 추가)
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
        
        // 중요 메시지 처리 로깅
        if (BOT_CONFIG.LOGGING.CORE_MESSAGES) {
            if (BOT_CONFIG.LOGGING.MESSAGE_CONTENT_DETAIL) {
                Log.i("[MSG] 메시지 처리: " + msg.room + " / " + msg.author.name + " - 전체내용: " + sanitizedContent);
            } else if (BOT_CONFIG.LOGGING.MESSAGE_CONTENT) {
                Log.i("[MSG] 메시지 처리: " + msg.room + " / " + msg.author.name + " - 내용: " + sanitizedContent.substring(0, 100) + (sanitizedContent.length > 100 ? "..." : ""));
            } else {
                Log.i("[MSG] 메시지 처리: " + msg.room + " / " + msg.author.name);
            }
        }
        
        sendMessage('analyze', {
            room: msg.room, text: sanitizedContent, sender: Utils.sanitizeText(msg.author.name), isGroupChat: msg.isGroupChat,
            channelId: msg.channelId ? msg.channelId.toString() : null, logId: msg.logId ? msg.logId.toString() : null,
            userHash: msg.author.hash, isMention: !!msg.isMention, timestamp: Utils.formatTimestamp(new Date()),
            botName: BOT_CONFIG.BOT_NAME, clientType: "MessengerBotR"
        });
    }

    function sendMessage(event, data) {
        data.auth = Auth.createAuthData();
        var packet = { event: event, data: data };
        _sendMessageInternal(packet);
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

    function sendAnalyzeMessage(messageData) {
        // 방 정보 업데이트
        if (messageData.channelId) {
            updateRoomInfo(messageData.channelId, messageData.room);
        }
        sendMessage('analyze', messageData);
    }

    // 🔴 주기적 정리 작업
    function _performPeriodicCleanup() {
        var now = Date.now();
        var dayMs = 24 * 60 * 60 * 1000;
        
        Log.i("[CLEANUP] 주기적 정리 작업 시작");
        
        // 1. 장기 비활성 방 정리 (30일 기준)
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
        
        // 2. 오래된 임시 파일 정리 (7일 기준)
        _cleanupOldTempFiles(BOT_CONFIG.TEMP_FILE_MAX_AGE_DAYS);
        
        // 3. 극단적 상황 대비 큐 크기 체크
        if (BOT_CONFIG.MAX_QUEUE_SIZE > 0 && messageQueue.length > BOT_CONFIG.MAX_QUEUE_SIZE) {
            var removed = messageQueue.splice(0, messageQueue.length - BOT_CONFIG.MAX_QUEUE_SIZE);
            Log.w("[CLEANUP] 극단적 큐 크기 제한으로 " + removed.length + "개 메시지 제거");
        }
        
        Log.i("[CLEANUP] 주기적 정리 작업 완료");
    }

    // 🔴 오래된 임시 파일 정리
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


    // 🔴 정기 작업 스케줄링
    function _schedulePeriodicTasks() {
        // 주기적 정리 작업 (24시간마다)
        cleanupTimeout = setInterval(function() {
            _performPeriodicCleanup();
        }, BOT_CONFIG.CLEANUP_INTERVAL);
                
        Log.i("[SCHEDULE] 정기 작업 스케줄링 완료 (정리: " + (BOT_CONFIG.CLEANUP_INTERVAL/3600000) + "시간, 모니터링: PING 기반)");
    }

    function cleanup() {
        Log.i("[CORE] 정리 시작");
        
        // 타이머 정리
        if (reconnectTimeout) {
            clearTimeout(reconnectTimeout);
            reconnectTimeout = null;
        }
        if (cleanupTimeout) {
            clearInterval(cleanupTimeout);
            cleanupTimeout = null;
        }
        
        // 소켓 정리
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
        
        // 🔴 정기 작업 스케줄링
        _schedulePeriodicTasks();
        
        // 지연된 서버 연결 시작 (컴파일 지연 방지)
        setTimeout(function() {
            Log.i("[CORE] 지연된 서버 연결 시작");
            _attemptConnectionToAllServers();
        }, BOT_CONFIG.INITIALIZATION_DELAY);
    }

    function start() {
        Log.i("[CORE] 봇 시작 (버전: " + BOT_CONFIG.VERSION + ", TTL: " + BOT_CONFIG.MESSAGE_TTL + "ms)");
        Log.i("[CORE] 우선순위 기반 서버 순서: " + _getSortedServers().map(function(s) { return s.name + "(P" + s.priority + ")"; }).join(", "));
        Log.i("[CORE] 재연결 설정: " + (BOT_CONFIG.MAX_RECONNECT_ATTEMPTS === -1 ? "무한" : BOT_CONFIG.MAX_RECONNECT_ATTEMPTS + "회"));
        initializeEventListeners();
    }

    return { 
        start: start, 
        findChannelIdByRoomName: findChannelIdByRoomName,
        updateRoomInfo: updateRoomInfo,
        sendAnalyzeMessage: sendAnalyzeMessage,
        cleanup: cleanup,
        initializeEventListeners: initializeEventListeners
    };
})();

// =============================================================================
// 6. 메인 모듈 (MainModule) - 컴파일 지연 방지
// =============================================================================
var MainModule = (function() {
    function initializeEventListeners() {
        Log.i("[MAIN] initializeEventListeners 호출 - 컴파일 지연 방지 적용");
        BotCore.initializeEventListeners();
    }

    return {
        initializeEventListeners: initializeEventListeners
    };
})();

// =============================================================================
// 7. MessengerBotR 표준 이벤트 핸들러
// =============================================================================

var bot = BotManager.getCurrentBot();

function onStartCompile() {
    Log.i("[SYSTEM] 컴파일 시작 - MessengerBotR Bridge v" + BOT_CONFIG.VERSION);
}

function onNotificationPosted(sbn, sm) {
    // 알림 처리 로직 (필요한 경우 구현)
}

function response(room, msg, sender, isGroupChat, replier, imageDB, packageName, threadId) {
    try {
        var channelId = imageDB && imageDB.getLastUid ? imageDB.getLastUid() : null;
        
        // 서버로 메시지 전송
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
        
        // 미디어 메시지 처리
        if (msg.startsWith("MEDIA_") && msg.includes("|||")) {
            var parts = msg.split("|||");
            if (parts.length >= 2) {
                var mediaType = parts[0]; // MEDIA_URL, MEDIA_BASE64 등
                
                Log.i("[MEDIA_RECEIVED] " + room + ": " + mediaType);
                // 미디어 처리 로직은 서버에서 담당
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
        // BotCore는 이미 MainModule.initializeEventListeners()에서 시작됨
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
    // 재시작 시 연결 재시도는 BotCore의 재연결 로직이 담당
}

function onDestroy(activity) {
    Log.i("[SYSTEM] 소멸");
    BotCore.cleanup();
}

function onBackPressed(activity) {
    return false;
}

// =============================================================================
// 봇 실행 - 컴파일 지연 방지 패턴 적용
// =============================================================================
Log.i("[SYSTEM] MessengerBotR Bridge v" + BOT_CONFIG.VERSION + " 로드 완료");
Log.i("[CONFIG] 봇 이름: " + BOT_CONFIG.BOT_NAME);
Log.i("[CONFIG] 서버 수: " + BOT_CONFIG.SERVER_LIST.length);
Log.i("[CONFIG] 미디어 디렉토리: " + BOT_CONFIG.MEDIA_TEMP_DIR);
Log.i("[CONFIG] 장기 실행 최적화: 무한재연결=" + (BOT_CONFIG.MAX_RECONNECT_ATTEMPTS === -1) + 
      ", 정리주기=" + (BOT_CONFIG.CLEANUP_INTERVAL/3600000) + "시간" +
      ", 방보관=" + BOT_CONFIG.ROOM_INACTIVE_DAYS + "일");

// 📋 FILE_SEND_TIMING 설정 예시 및 사용법
/*
🔧 대기시간 설정 최적화 가이드:

1. 📱 일반적인 환경 (기본값):
   FILE_SEND_TIMING: {
       BASE_WAIT_TIME: 1500,
       SIZE_BASED_WAIT_PER_MB: 2000,
       COUNT_BASED_WAIT_PER_FILE: 500,
       SINGLE_FILE: { MIN_WAIT: 4000, MAX_WAIT: 6000 },
       MULTI_FILE: { MIN_WAIT: 3000, MAX_WAIT: 15000 }
   }

2. 🐌 느린 기기/네트워크 환경:
   FILE_SEND_TIMING: {
       BASE_WAIT_TIME: 2500,           // 기본 대기 증가
       SIZE_BASED_WAIT_PER_MB: 3000,   // 용량당 대기 증가
       COUNT_BASED_WAIT_PER_FILE: 800,  // 파일 개수당 대기 증가
       SINGLE_FILE: { MIN_WAIT: 6000, MAX_WAIT: 10000 },
       MULTI_FILE: { MIN_WAIT: 5000, MAX_WAIT: 25000 }
   }

3. 🚀 빠른 기기/네트워크 환경:
   FILE_SEND_TIMING: {
       BASE_WAIT_TIME: 1000,           // 기본 대기 감소
       SIZE_BASED_WAIT_PER_MB: 1500,   // 용량당 대기 감소
       COUNT_BASED_WAIT_PER_FILE: 300,  // 파일 개수당 대기 감소
       SINGLE_FILE: { MIN_WAIT: 3000, MAX_WAIT: 5000 },
       MULTI_FILE: { MIN_WAIT: 2000, MAX_WAIT: 12000 }
   }

4. 🖼️ 대용량 이미지 전송 최적화:
   FILE_SEND_TIMING: {
       BASE_WAIT_TIME: 2000,
       SIZE_BASED_WAIT_PER_MB: 2500,   // 용량당 대기 증가
       COUNT_BASED_WAIT_PER_FILE: 400,
       SINGLE_FILE: { MIN_WAIT: 5000, MAX_WAIT: 12000 },  // 최대 대기 증가
       MULTI_FILE: { MIN_WAIT: 4000, MAX_WAIT: 20000 }    // 최대 대기 증가
   }

📊 계산 공식:
- 단일 파일: BASE_WAIT_TIME + (파일크기MB × SIZE_BASED_WAIT_PER_MB)
- 멀티 파일: BASE_WAIT_TIME + (총파일크기MB × SIZE_BASED_WAIT_PER_MB) + ((파일개수-1) × COUNT_BASED_WAIT_PER_FILE)
- 최종 대기시간: Math.min(Math.max(계산값, MIN_WAIT), MAX_WAIT)

🔍 예시:
- 2MB 단일 파일: 1500 + (2 × 2000) = 5500ms (4초~6초 범위 내)
- 3MB + 1MB + 2MB (3개 파일): 1500 + (6 × 2000) + (2 × 500) = 14500ms (3초~15초 범위 내)
*/

// 컴파일 지연 방지를 위한 MainModule.initializeEventListeners() 호출
MainModule.initializeEventListeners();