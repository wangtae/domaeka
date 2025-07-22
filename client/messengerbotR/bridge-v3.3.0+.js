/**
 * MessengerBotR 클라이언트 - 카카오톡 브릿지 스크립트 v3.3.0
 * 
 * @description
 * 카카오톡과 서버 간의 통신을 중개하는 브릿지 클라이언트 스크립트입니다.
 * v3.3.0 통합 메시지 프로토콜 적용 - JSON + Raw 데이터 구조로 대용량 미디어 처리 최적화
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
 * @version 3.3.0
 * @author kkobot.com / domaeka.dev
 * @improvements v3.3.0 통합 메시지 프로토콜, 위치 기반 데이터 추출, Base64 텍스트 인코딩
 */

// =============================================================================
// 1. 설정 모듈 (BOT_CONFIG) - 장기 실행 안정성 강화
// =============================================================================
var BOT_CONFIG = {
    // 기본 정보
    VERSION: '3.3.0',
    BOT_NAME: 'LOA.i',
    CLIENT_TYPE: 'MessengerBotR',
    PROTOCOL_VERSION: 3,  // v3.3.0 프로토콜 버전

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
        MESSAGE_CONTENT_DETAIL: false   // 송수신 메시지 전체 내용 표시 (디버깅용)
    },

    // 🔴 파일 전송 대기시간 설정 (사용자 조절 가능)
    FILE_SEND_TIMING: {
        BASE_WAIT_TIME: 1500,           // 기본 대기시간 (ms)
        SIZE_BASED_WAIT_PER_MB: 2000,   // MB당 추가 대기시간 (ms)
        COUNT_BASED_WAIT_PER_FILE: 500, // 파일 개수당 추가 대기시간 (ms)
        SINGLE_FILE: {
            MIN_WAIT: 4000,             // 단일 파일 최소 대기시간 (ms)
            MAX_WAIT: 6000              // 단일 파일 최대 대기시간 (ms)
        },
        MULTI_FILE: {
            MIN_WAIT: 3000,             // 멀티 파일 최소 대기시간 (ms)
            MAX_WAIT: 15000             // 멀티 파일 최대 대기시간 (ms)
        }
    },

    // 패키지명
    PACKAGES: {
        App: App,
        File: java.io.File,
        FileInputStream: java.io.FileInputStream,
        FileOutputStream: java.io.FileOutputStream,
        InputStreamReader: java.io.InputStreamReader,
        BufferedReader: java.io.BufferedReader,
        Socket: java.net.Socket,
        InetSocketAddress: java.net.InetSocketAddress,
        Thread: java.lang.Thread,
        Base64: android.util.Base64,
        Log: android.util.Log,
        Settings: android.provider.Settings,
        Intent: android.content.Intent,
        Handler: android.os.Handler,
        Looper: android.os.Looper,
        Uri: android.net.Uri,
        Build: android.os.Build,
        FileProvider: androidx.core.content.FileProvider,
        MediaScannerConnection: android.media.MediaScannerConnection,
        StrictMode: android.os.StrictMode,
        URL: java.net.URL,
        PackageManager: android.content.pm.PackageManager,
        Environment: android.os.Environment,
        KeyChain: android.security.KeyChain
    }
};

// =============================================================================
// 2. 메시지 타입 정의 (v3.3.0 통합 프로토콜)
// =============================================================================
var MESSAGE_TYPES = {
    TEXT: "text",           // 일반 텍스트 메시지
    IMAGE: "image",         // jpg, png, gif, webp 등
    AUDIO: "audio",         // mp3, wav, m4a 등
    VIDEO: "video",         // mp4, avi, mov 등
    DOCUMENT: "document",   // pdf, doc, xls 등
    ARCHIVE: "archive"      // zip, rar, 7z 등
};

// 카테고리별 지원 포맷
var SUPPORTED_FORMATS = {
    image: ["jpg", "jpeg", "png", "gif", "webp", "bmp"],
    audio: ["mp3", "wav", "m4a", "ogg", "flac"],
    video: ["mp4", "avi", "mov", "mkv", "webm"],
    document: ["pdf", "doc", "docx", "xls", "xlsx", "ppt", "pptx", "txt"],
    archive: ["zip", "rar", "7z", "tar", "gz"]
};

// v3.3.0 프로토콜이 적용되는 이벤트
var NEW_PROTOCOL_EVENTS = ["messageResponse", "scheduleMessage", "broadcastMessage"];

// =============================================================================
// 3. 유틸리티 모듈 (Utils)
// =============================================================================
var Utils = (function() {
    var P = BOT_CONFIG.PACKAGES;

    function generateUniqueId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2, 9);
    }

    function formatTimestamp(date) {
        var d = date || new Date();
        var year = d.getFullYear();
        var month = ('0' + (d.getMonth() + 1)).slice(-2);
        var day = ('0' + d.getDate()).slice(-2);
        var hour = ('0' + d.getHours()).slice(-2);
        var minute = ('0' + d.getMinutes()).slice(-2);
        var second = ('0' + d.getSeconds()).slice(-2);
        return year + '-' + month + '-' + day + ' ' + hour + ':' + minute + ':' + second;
    }
    
    // UTC 타임스탬프 포맷팅 (ISO 8601)
    function formatUTCTimestamp(date) {
        var d = date || new Date();
        return d.toISOString().replace(/\.\d{3}/, '');
    }

    function formatDate(date, format) { var d = date || new Date(); return format.replace('YYYY', d.getFullYear()).replace('MM', ('0' + (d.getMonth() + 1)).slice(-2)).replace('DD', ('0' + d.getDate()).slice(-2)); }

    function sanitizeText(text) {
        if (!text) return '';
        return String(text).replace(/[\r\n]+/g, ' ').replace(/\s+/g, ' ').trim();
    }

    function hmacSha256(key, message) {
        try {
            var keySpec = new javax.crypto.spec.SecretKeySpec(key.getBytes("UTF-8"), "HmacSHA256");
            var mac = javax.crypto.Mac.getInstance("HmacSHA256");
            mac.init(keySpec);
            var result = mac.doFinal(message.getBytes("UTF-8"));
            return bytesToHex(result);
        } catch (e) {
            Log.e("[HMAC] 에러: " + e); 
            return null;
        }
    }

    function bytesToHex(bytes) {
        var hexChars = "0123456789abcdef";
        var hex = "";
        for (var i = 0; i < bytes.length; i++) {
            var b = bytes[i] & 0xFF;
            hex += hexChars.charAt(b >> 4) + hexChars.charAt(b & 0x0F);
        }
        return hex;
    }

    function parseJSON(jsonStr) {
        try { 
            return JSON.parse(jsonStr); 
        } catch (e) { 
            Log.e("[JSON] 파싱 실패: " + e); 
            return null; 
        }
    }

    // Base64 인코딩/디코딩 함수 추가
    function base64Encode(text) {
        try {
            var bytes = new java.lang.String(text).getBytes("UTF-8");
            return P.Base64.encodeToString(bytes, P.Base64.NO_WRAP);
        } catch (e) {
            Log.e("[BASE64] 인코딩 실패: " + e);
            return text;
        }
    }

    function base64Decode(base64) {
        try {
            var bytes = P.Base64.decode(base64, P.Base64.DEFAULT);
            return new java.lang.String(bytes, "UTF-8");
        } catch (e) {
            Log.e("[BASE64] 디코딩 실패: " + e);
            return base64;
        }
    }

    // 메시지 로깅 포맷
    function formatMessageLog(event, data, rawContent) {
        var logPrefix = "[" + event.toUpperCase() + "] ";
        
        switch(data.message_type) {
            case MESSAGE_TYPES.TEXT:
                // Base64 디코딩 후 출력
                var decodedText = rawContent;
                if (data.content_encoding === "base64") {
                    decodedText = base64Decode(rawContent);
                }
                
                // 최대 1000바이트로 제한
                if (decodedText.length > 1000) {
                    decodedText = decodedText.substring(0, 1000) + "... (truncated)";
                }
                
                return logPrefix + "텍스트 메시지: " + decodedText;
                
            case MESSAGE_TYPES.IMAGE:
                var positions = data.message_positions;
                var imageCount = positions.length - 1;
                var totalSize = positions[positions.length - 1];
                
                return logPrefix + "이미지: " + imageCount + "개, 총 " + 
                      Math.round(totalSize / 1024) + "KB";
                
            case MESSAGE_TYPES.AUDIO:
                var positions = data.message_positions;
                var audioCount = positions.length - 1;
                
                return logPrefix + "오디오: " + audioCount + "개 파일";
                
            case MESSAGE_TYPES.DOCUMENT:
                var positions = data.message_positions;
                var docCount = positions.length - 1;
                
                return logPrefix + "문서: " + docCount + "개 파일 (" + 
                      data.message_format + ")";
                      
            default:
                return logPrefix + data.message_type + " 메시지";
        }
    }

    return {
        generateUniqueId: generateUniqueId,
        formatTimestamp: formatTimestamp,
        formatUTCTimestamp: formatUTCTimestamp,
        formatDate: formatDate,
        sanitizeText: sanitizeText,
        hmacSha256: hmacSha256,
        parseJSON: parseJSON,
        base64Encode: base64Encode,
        base64Decode: base64Decode,
        formatMessageLog: formatMessageLog
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
            var policy = new P.StrictMode.VmPolicy.Builder().build();
            P.StrictMode.setVmPolicy(policy);
        } catch (e) {}
    }

    function _sendIntentToKakaoTalk(room, uris, mimeType) {
        try {
            var intent = new P.Intent(P.Intent.ACTION_SEND_MULTIPLE);
            intent.setType(mimeType);
            intent.setPackage(BOT_CONFIG.KAKAOTALK_PACKAGE_NAME);
            intent.putParcelableArrayListExtra(P.Intent.EXTRA_STREAM, uris);
            intent.addFlags(P.Intent.FLAG_GRANT_READ_URI_PERMISSION);
            intent.addFlags(P.Intent.FLAG_ACTIVITY_NEW_TASK);
            context.startActivity(intent);
        } catch (e) {
            Log.e("[SEND] 인텐트 전송 실패: " + e);
        }
    }

    function _calculateSendDelay(fileCount, totalSize, isGroup) {
        var T = BOT_CONFIG.FILE_SEND_TIMING;
        var sizeInMB = Math.max(1, Math.round(totalSize / 1024 / 1024));
        var baseDelay = T.BASE_WAIT_TIME + (sizeInMB * T.SIZE_BASED_WAIT_PER_MB);
        var countDelay = (fileCount > 1) ? ((fileCount - 1) * T.COUNT_BASED_WAIT_PER_FILE) : 0;
        var totalDelay = baseDelay + countDelay;
        
        var limits = (fileCount === 1) ? T.SINGLE_FILE : T.MULTI_FILE;
        totalDelay = Math.min(Math.max(totalDelay, limits.MIN_WAIT), limits.MAX_WAIT);
        
        Log.d("[TIMING] 계산: " + fileCount + "개 파일, " + Math.round(totalSize/1024/1024) + 
              "MB, " + (isGroup ? "그룹" : "개인") + " → 대기시간: " + totalDelay + "ms");
        
        return totalDelay;
    }

    function _scheduleFileCleanup(filesToDelete, delay) {
        if (!filesToDelete || filesToDelete.length === 0) return;
        setTimeout(function() {
            filesToDelete.forEach(function(filePath) {
                try {
                    var file = new P.File(filePath);
                    if (file.exists() && file.delete()) {
                        Log.d("[CLEANUP] 임시 파일 삭제: " + filePath);
                    }
                } catch (e) {
                    Log.e("[CLEANUP] 파일 삭제 실패: " + e);
                }
            });
        }, delay + 5000); // 전송 대기시간 + 5초 후 삭제
    }

    function _processMedia(room, mediaList, mimeTypePrefix, isGroup, serverWaitTime) {
        var preparedFiles = [];
        var filesToDelete = [];
        var totalSize = 0;

        for (var i = 0; i < mediaList.length; i++) {
            var prepared = _prepareFile(mediaList[i], i);
            if (prepared) {
                preparedFiles.push(prepared);
                if (prepared.downloaded) filesToDelete.push(prepared.path);
                var file = new P.File(prepared.path);
                totalSize += file.length();
            }
        }

        if (preparedFiles.length === 0) {
            Log.e("[MEDIA] 준비된 파일이 없습니다.");
            return;
        }

        _disableStrictMode();

        var uris = new java.util.ArrayList();
        var commonMimeType = null;
        for (var j = 0; j < preparedFiles.length; j++) {
            var file = preparedFiles[j];
            var uri = _createSafeFileUri(file.path);
            if (uri) {
                uris.add(uri);
                if (!commonMimeType || commonMimeType === "*/*") {
                    if (file.mimeType.startsWith(mimeTypePrefix)) {
                        commonMimeType = file.mimeType;
                    } else {
                        commonMimeType = "*/*";
                    }
                }
            }
        }

        if (uris.size() === 0) {
            Log.e("[MEDIA] URI 생성 실패");
            return;
        }

        _sendIntentToKakaoTalk(room, uris, commonMimeType || mimeTypePrefix + "/*");

        // v3.3.0: 서버가 지정한 대기시간이 있으면 우선 사용
        // serverWaitTime이 명시적으로 전달되고 0보다 큰 경우에만 사용
        var sendDelay;
        if (typeof serverWaitTime === 'number' && serverWaitTime > 0) {
            sendDelay = serverWaitTime;
            Log.d("[TIMING] 서버 지정 대기시간 사용: " + sendDelay + "ms");
        } else {
            // serverWaitTime이 없거나 0 이하인 경우 클라이언트 계산값 사용
            sendDelay = _calculateSendDelay(preparedFiles.length, totalSize, isGroup);
        }
        
        setTimeout(function() {
            bot.send(room, ""); // 빈 메시지로 돌아오기
        }, sendDelay);

        _scheduleFileCleanup(filesToDelete, sendDelay);
    }

    // v3.3.0 새로운 처리 함수들
    function processImages(data, imageDataList) {
        Log.i("[MEDIA] 이미지 " + imageDataList.length + "개 처리 시작");
        _processMedia(data.room, imageDataList, "image", data.is_group_chat, data.media_wait_time);
    }

    function processAudios(data, audioDataList) {
        Log.i("[MEDIA] 오디오 " + audioDataList.length + "개 처리 시작");
        _processMedia(data.room, audioDataList, "audio", data.is_group_chat, data.media_wait_time);
    }

    function processVideos(data, videoDataList) {
        Log.i("[MEDIA] 비디오 " + videoDataList.length + "개 처리 시작");
        _processMedia(data.room, videoDataList, "video", data.is_group_chat, data.media_wait_time);
    }

    function processDocuments(data, docDataList) {
        Log.i("[MEDIA] 문서 " + docDataList.length + "개 처리 시작");
        _processMedia(data.room, docDataList, "application", data.is_group_chat, data.media_wait_time);
    }


    return {
        processImages: processImages,
        processAudios: processAudios,
        processVideos: processVideos,
        processDocuments: processDocuments
    };
})();

// =============================================================================
// 5. 인증 모듈 (Auth)
// =============================================================================
var Auth = (function() {
    var P = BOT_CONFIG.PACKAGES;
    var deviceId = null;

    function getDeviceId() {
        if (!deviceId) {
            try { deviceId = P.Settings.Secure.getString(App.getContext().getContentResolver(), P.Settings.Secure.ANDROID_ID); } 
            catch (e) { deviceId = "unknown_device"; }
        }
        return deviceId;
    }

    function generateAuthKey(room, message, timestamp) {
        var deviceId = getDeviceId();
        var dataToSign = [BOT_CONFIG.BOT_NAME, room, message, timestamp, deviceId, BOT_CONFIG.BOT_SPECIFIC_SALT].join(":");
        return Utils.hmacSha256(BOT_CONFIG.SECRET_KEY, dataToSign);
    }

    function createAuthData() {
        var timestamp = Utils.formatTimestamp(new Date());
        return {
            timestamp: timestamp,
            deviceId: getDeviceId(),
            signature: Utils.hmacSha256(BOT_CONFIG.SECRET_KEY, BOT_CONFIG.BOT_NAME + ":" + timestamp + ":" + getDeviceId())
        };
    }

    return {
        getDeviceId: getDeviceId,
        generateAuthKey: generateAuthKey,
        createAuthData: createAuthData
    };
})();

// =============================================================================
// 6. 코어 서버 통신 모듈 (BotCore) - v3.3.0 프로토콜 적용
// =============================================================================
var BotCore = (function() {
    var P = BOT_CONFIG.PACKAGES;
    var socket = null, inputStream = null, outputStream = null, readerThread = null;
    var isConnected = false, isConnecting = false, isReconnecting = false;
    var reconnectAttempts = 0, reconnectTimeout = null, currentServerIndex = 0;
    var messageQueue = [];
    var isProcessingQueue = false;
    var lastPingTime = Date.now();
    var cleanupInterval = null;
    var roomLastActive = {};
    var currentRooms = {};  // 채널ID-방이름 매핑

    // 시작 함수 
    function initializeEventListeners() { _attemptConnectionToAllServers(); }

    // 연결 시도
    function _attemptConnectionToAllServers() {
        if (isConnecting) return;
        isConnecting = true;

        var servers = BOT_CONFIG.SERVER_LIST.slice().sort(function(a, b) { return a.priority - b.priority; });
        _tryNextServer(servers, 0);
    }

    // 다음 서버로 연결 시도
    function _tryNextServer(servers, index) {
        if (index >= servers.length) {
            isConnecting = false;
            _scheduleReconnect();
            return;
        }

        var server = servers[index];
        Log.i("[CORE] 서버 연결 시도: " + server.name + " (" + server.host + ":" + server.port + ")");
        
        _connectToServer(server.host, server.port, function(success) {
            if (success) {
                isConnecting = false;
                Log.i("[CORE] 서버 연결 성공: " + server.name);
            } else {
                _tryNextServer(servers, index + 1);
            }
        });
    }

    // 실제 서버 연결
    function _connectToServer(host, port, callback) {
        if (isConnected) { callback(true); return; }

        try {
            socket = new P.Socket();
            socket.setSoTimeout(60000);
            socket.setKeepAlive(true);
            socket.connect(new P.InetSocketAddress(host, port), 5000);
            
            inputStream = new P.BufferedReader(new P.InputStreamReader(socket.getInputStream(), "UTF-8"));
            outputStream = socket.getOutputStream();
            
            _sendHandshake();
            _startReaderThread();
            
            isConnected = true;
            reconnectAttempts = 0;
            currentServerIndex = BOT_CONFIG.SERVER_LIST.findIndex(function(s) { return s.host === host && s.port === port; });
            callback(true);
            
            _processMessageQueue();
            
            // 장기 실행 안정성: 정리 작업 시작
            if (!cleanupInterval) {
                cleanupInterval = setInterval(function() { _performCleanup(); }, BOT_CONFIG.CLEANUP_INTERVAL);
            }
        } catch (e) {
            Log.e("[CORE] 연결 실패: " + e);
            _closeSocket();
            callback(false);
        }
    }

    // 핸드셰이크 전송 - v3.3.0 프로토콜 버전 + v3.2.0 강화된 인증
    function _sendHandshake() {
        try {
            var deviceIP = socket && socket.getLocalAddress() ? socket.getLocalAddress().getHostAddress() : "unknown";
            var deviceInfo = _getDeviceInfo();
            
            var handshake = {
                clientType: BOT_CONFIG.CLIENT_TYPE,
                botName: BOT_CONFIG.BOT_NAME, 
                version: BOT_CONFIG.VERSION, 
                deviceID: Auth.getDeviceId(),
                deviceIP: deviceIP,
                deviceInfo: deviceInfo,
                protocolVersion: BOT_CONFIG.PROTOCOL_VERSION,
                supportedMessageTypes: Object.values(MESSAGE_TYPES)
            };
            
            if (BOT_CONFIG.LOGGING.CONNECTION_EVENTS) {
                Log.i("[HANDSHAKE] 전송: " + JSON.stringify(handshake));
            }
            outputStream.write((JSON.stringify(handshake) + "\n").getBytes("UTF-8"));
            outputStream.flush();
        } catch (e) {
            Log.e("[HANDSHAKE] 전송 실패: " + e);
        }
    }
    
    // 디바이스 정보 생성
    function _getDeviceInfo() {
        try {
            var P = BOT_CONFIG.PACKAGES;
            var model = P.Build.MODEL || "unknown";
            var brand = P.Build.BRAND || "unknown";
            var version = P.Build.VERSION.RELEASE || "unknown";
            var sdk = P.Build.VERSION.SDK_INT || "unknown";
            
            return brand + " " + model + " (Android " + version + ", API " + sdk + ")";
        } catch (e) {
            Log.e("[DEVICE] 디바이스 정보 가져오기 실패: " + e);
            return "unknown device";
        }
    }

    // 리더 스레드 시작
    function _startReaderThread() {
        readerThread = new P.Thread(new java.lang.Runnable({
            run: function() {
                try {
                    while (isConnected && inputStream) {
                        var line = inputStream.readLine();
                        if (line === null) {
                            Log.e("[READER] 서버 연결 종료");
                            break;
                        }
                        if (line.trim()) {
                            _handleServerResponse(line);
                        }
                    }
                } catch (e) {
                    if (isConnected) {
                        Log.e("[READER] 읽기 오류: " + e);
                    }
                }
                _closeSocket();
                _scheduleReconnect();
            }
        }));
        readerThread.start();
    }

    // 소켓 종료
    function _closeSocket() {
        try {
            isConnected = false;
            
            // 1. 리더 스레드 종료
            if (readerThread && readerThread.isAlive()) {
                readerThread.interrupt();
                try { readerThread.join(BOT_CONFIG.THREAD_JOIN_TIMEOUT); } catch (e) {}
                readerThread = null;
            }
            
            // 2. 스트림 종료
            if (inputStream) { 
                inputStream.close(); 
                inputStream = null; 
            }
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

    // v3.3.0 메시지 전송 - JSON + Raw 데이터 구조
    function _sendMessageInternal(event, data, messageContent) {
        if (!isConnected) {
            // TTL 기능: 타임스탬프 추가
            var queueItem = {
                event: event,
                data: data,
                messageContent: messageContent,
                timestamp: Date.now()
            };
            messageQueue.push(queueItem);
            if (BOT_CONFIG.LOGGING.QUEUE_OPERATIONS) {
                Log.i("[QUEUE] 메시지 큐에 추가 (TTL: " + BOT_CONFIG.MESSAGE_TTL + "ms): " + event);
            }
            return false;
        }
        try {
            var packet = "";
            
            if (messageContent && event === "message") {
                // v3.3.0 새 프로토콜: message 이벤트에 적용
                data.message_positions = [0, messageContent.length];
                var jsonPart = JSON.stringify({event: event, data: data});
                packet = jsonPart + messageContent + "\n";
            } else {
                // 기존 프로토콜: ping, handshake 등
                packet = JSON.stringify({event: event, data: data}) + "\n";
            }
            
            outputStream.write(packet.getBytes("UTF-8"));
            outputStream.flush();
            
            // 중요 메시지 전송 로깅
            if (BOT_CONFIG.LOGGING.MESSAGE_TRANSFER) {
                if (messageContent) {
                    Log.i("[SEND] " + event + " - " + Utils.formatMessageLog(event, data, messageContent));
                } else {
                    Log.i("[SEND] " + event + " 메시지 전송 완료");
                }
            }
            return true;
        } catch (e) {
            Log.e("[SEND] 메시지 전송 실패: " + e);
            // TTL 기능: 실패한 메시지도 타임스탬프와 함께 큐에 추가
            var queueItem = {
                event: event,
                data: data,
                messageContent: messageContent,
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
                Log.w("[TTL] 만료된 메시지 폐기: " + queueItem.event + " (나이: " + (now - queueItem.timestamp) + "ms)");
                continue;
            }
            
            // 유효한 메시지 전송
            if (_sendMessageInternal(queueItem.event, queueItem.data, queueItem.messageContent)) {
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

    // v3.3.0 서버 응답 처리 - 새 프로토콜 적용
    function _handleServerResponse(rawMsg) {
        try {
            var packet = null;
            var messageData = null;
            
            // JSON 끝 위치 찾기
            var jsonEndIndex = rawMsg.lastIndexOf('}');
            if (jsonEndIndex === -1) {
                Log.e("[RESPONSE] 잘못된 패킷 형식");
                return;
            }
            
            // JSON 부분만 추출하여 파싱
            var jsonPart = rawMsg.substring(0, jsonEndIndex + 1);
            packet = JSON.parse(jsonPart);
            
            var event = packet.event;
            var data = packet.data;
            if (!data) { 
                Log.e("[RESPONSE] 데이터 없음"); 
                return; 
            }
            
            // v3.3.0 프로토콜 적용 확인
            if (data.message_positions && NEW_PROTOCOL_EVENTS.indexOf(event) !== -1) {
                var baseOffset = jsonEndIndex + 1;
                var positions = data.message_positions;
                
                if (positions.length === 2) {
                    // 단일 메시지 - 끝 위치 무시하고 전체 사용
                    messageData = rawMsg.substring(baseOffset);
                    if (messageData.endsWith('\n')) {
                        messageData = messageData.substring(0, messageData.length - 1);
                    }
                } else if (positions.length > 2) {
                    // 멀티 메시지 - 위치 배열로 추출
                    var messages = [];
                    for (var i = 0; i < positions.length - 1; i++) {
                        var start = baseOffset + positions[i];
                        var end = baseOffset + positions[i + 1];
                        messages.push(rawMsg.substring(start, end));
                    }
                    messageData = messages;
                }
            }
            
            // 중요 메시지 수신 로깅
            if (BOT_CONFIG.LOGGING.MESSAGE_TRANSFER && event !== 'ping') {
                if (messageData) {
                    Log.i("[RECV] " + event + " - " + Utils.formatMessageLog(event, data, messageData));
                } else {
                    Log.i("[RECV] " + event + " 메시지 수신");
                }
            }

            // 이벤트별 처리
            if (event === 'messageResponse' || event === 'scheduleMessage' || event === 'broadcastMessage') {
                // 스케줄 메시지 수신 시 상세 로깅
                if (event === 'scheduleMessage') {
                    Log.i("[SCHEDULE] 스케줄 메시지 수신 - 방: " + data.room + ", 타입: " + data.message_type);
                }
                _handleMessageEvent(event, data, messageData);
            } else if (event === 'ping') {
                _handlePingEvent(data);
            } else {
                // 기타 이벤트 (handshake 응답, error 등)
                Log.i("[EVENT] " + event + " 수신");
            }
            
        } catch (e) {
            Log.e("[RESPONSE] 처리 오류: " + e + "\n원본: " + rawMsg);
        }
    }

    // 메시지 이벤트 처리 (v3.3.0)
    function _handleMessageEvent(event, data, messageData) {
        // 방 활성 시간 업데이트
        if (data.room) {
            roomLastActive[data.room] = Date.now();
        }

        switch(data.message_type) {
            case MESSAGE_TYPES.TEXT:
                // Base64 디코딩
                var textContent = messageData;
                if (data.content_encoding === "base64") {
                    textContent = Utils.base64Decode(messageData);
                }
                bot.send(data.room, textContent);
                break;
                
            case MESSAGE_TYPES.IMAGE:
                if (Array.isArray(messageData)) {
                    MediaHandler.processImages(data, messageData);
                } else {
                    MediaHandler.processImages(data, [messageData]);
                }
                break;
                
            case MESSAGE_TYPES.AUDIO:
                if (Array.isArray(messageData)) {
                    MediaHandler.processAudios(data, messageData);
                } else {
                    MediaHandler.processAudios(data, [messageData]);
                }
                break;
                
            case MESSAGE_TYPES.VIDEO:
                if (Array.isArray(messageData)) {
                    MediaHandler.processVideos(data, messageData);
                } else {
                    MediaHandler.processVideos(data, [messageData]);
                }
                break;
                
            case MESSAGE_TYPES.DOCUMENT:
                if (Array.isArray(messageData)) {
                    MediaHandler.processDocuments(data, messageData);
                } else {
                    MediaHandler.processDocuments(data, [messageData]);
                }
                break;
                
            default:
                Log.w("[MESSAGE] 알 수 없는 메시지 타입: " + data.message_type);
        }
    }

    // Ping 이벤트 처리
    function _handlePingEvent(data) {
        lastPingTime = Date.now();
        
        // 🔴 ping 응답 (모니터링 데이터 포함 여부에 따라 단일 응답)
        var pingData = {
            bot_name: data.bot_name,
            server_timestamp: data.server_timestamp,
            timestamp: Utils.formatUTCTimestamp(),
            timezone: "Asia/Seoul"
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
                    cpu_usage: 0,  // CPU 사용률은 Android에서 직접 측정 어려움
                    memory_usage: Math.round(usedMemory),
                    memory_percent: Math.round(memoryPercent * 10) / 10,
                    uptime: Math.round((Date.now() - startTime) / 1000),
                    queue_size: messageQueue.length,
                    active_rooms: Object.keys(roomLastActive).length,
                    total_memory: Math.round(totalMemory),
                    max_memory: Math.round(maxMemory)
                };
                
                // ping 데이터에 모니터링 정보 추가
                for (var key in monitoringData) {
                    pingData[key] = monitoringData[key];
                }
                
                if (BOT_CONFIG.LOGGING.PING_EVENTS && BOT_CONFIG.LOGGING.RESOURCE_INFO) {
                    Log.i("[PING] 모니터링 - 메모리: " + monitoringData.memory_usage + "/" + 
                          monitoringData.max_memory + "MB (" + monitoringData.memory_percent + "%), " +
                          "큐: " + monitoringData.queue_size + ", 활성방: " + monitoringData.active_rooms);
                }
            } catch (e) {
                Log.e("[PING] 모니터링 데이터 수집 실패: " + e);
            }
        }
        
        _sendMessageInternal('pong', pingData);
        
        if (BOT_CONFIG.LOGGING.PING_EVENTS) {
            Log.d("[PING] 응답 전송 완료");
        }
    }

    // 장기 실행 안정성: 정리 작업
    function _performCleanup() {
        try {
            var now = Date.now();
            var cleanedRooms = 0;
            var cleanedFiles = 0;
            
            // 1. 비활성 방 정리
            for (var room in roomLastActive) {
                if (now - roomLastActive[room] > BOT_CONFIG.ROOM_INACTIVE_DAYS * 86400000) {
                    delete roomLastActive[room];
                    cleanedRooms++;
                }
            }
            
            // 2. 오래된 임시 파일 정리
            try {
                var tempDir = new P.File(BOT_CONFIG.MEDIA_TEMP_DIR);
                if (tempDir.exists() && tempDir.isDirectory()) {
                    var files = tempDir.listFiles();
                    for (var i = 0; i < files.length; i++) {
                        var file = files[i];
                        if (file.isFile() && (now - file.lastModified()) > BOT_CONFIG.TEMP_FILE_MAX_AGE_DAYS * 86400000) {
                            if (file.delete()) {
                                cleanedFiles++;
                            }
                        }
                    }
                }
            } catch (e) {
                Log.e("[CLEANUP] 파일 정리 실패: " + e);
            }
            
            // 3. 가비지 컬렉션 수동 실행
            try {
                java.lang.System.gc();
            } catch (e) {}
            
            if (cleanedRooms > 0 || cleanedFiles > 0) {
                Log.i("[CLEANUP] 정리 완료 - 방: " + cleanedRooms + "개, 파일: " + cleanedFiles + "개");
            }
            
        } catch (e) {
            Log.e("[CLEANUP] 정리 작업 실패: " + e);
        }
    }

    // 채널ID로 방 이름 찾기
    function findChannelIdByRoomName(roomName) {
        for (var cid in currentRooms) {
            if (currentRooms[cid].room === roomName) return cid;
        }
        return null;
    }

    // 방 정보 업데이트
    function updateRoomInfo(channelId, roomName) {
        currentRooms[channelId] = {
            room: roomName,
            lastActivity: Date.now()
        };
        roomLastActive[roomName] = Date.now();
    }

    // 메시지 전송 함수 (v3.3.0 - analyze 대신 message 이벤트 사용)
    function sendMessage(messageData) {
        // 방 정보 업데이트
        if (messageData.channelId && messageData.room) {
            updateRoomInfo(messageData.channelId, messageData.room);
        }
        
        // v3.3.0: 텍스트 메시지는 Base64로 인코딩
        var encodedContent = Utils.base64Encode(messageData.text);
        
        var data = {
            room: messageData.room,
            channel_id: messageData.channelId ? String(messageData.channelId) : null,
            message_type: "text",
            message_positions: [0, encodedContent.length],
            media_wait_time: 0,
            timestamp: Utils.formatUTCTimestamp(),
            timezone: "Asia/Seoul",
            sender: messageData.sender,
            logId: messageData.logId || Utils.generateUniqueId(),
            userHash: messageData.userHash || Utils.generateUniqueId(),
            isMention: messageData.isMention || false,
            botName: messageData.botName,
            clientType: BOT_CONFIG.CLIENT_TYPE || "MessengerBotR",
            content_encoding: "base64",
            is_group_chat: messageData.isGroupChat,
            auth: Auth.createAuthData()
        };
        
        _sendMessageInternal("message", data, encodedContent);
        _processMessageQueue();
    }

    // 레거시 호환성을 위한 함수 (삭제 예정)
    function sendAnalyzeMessage(messageData) {
        Log.w("[LEGACY] analyze 이벤트는 v3.3.0에서 message 이벤트로 대체됩니다.");
        sendMessage(messageData);
    }

    // 정리 함수
    function cleanup() {
        try {
            Log.i("[CORE] 정리 시작");
            
            // 재연결 취소
            if (reconnectTimeout) {
                clearTimeout(reconnectTimeout);
                reconnectTimeout = null;
            }
            
            // 정리 작업 중단
            if (cleanupInterval) {
                clearInterval(cleanupInterval);
                cleanupInterval = null;
            }
            
            // 소켓 종료
            _closeSocket();
            
            // 큐 초기화
            messageQueue = [];
            
            Log.i("[CORE] 정리 완료");
        } catch (e) {
            Log.e("[CORE] 정리 실패: " + e);
        }
    }

    // startTime 정의 추가
    var startTime = Date.now();

    return {
        initializeEventListeners: initializeEventListeners,
        sendMessage: sendMessage,
        sendAnalyzeMessage: sendAnalyzeMessage,  // 레거시 호환성
        findChannelIdByRoomName: findChannelIdByRoomName,
        updateRoomInfo: updateRoomInfo,
        cleanup: cleanup
    };
})();

// =============================================================================
// 7. 봇 매니저 모듈 (BotManager) - 추상화 계층
// =============================================================================
var BotManager = (function() {
    var bot = null;
    var isBotAvailable = false;

    // 초기화 시도
    function _initializeBot() {
        try {
            if (typeof Bot !== 'undefined' && Bot !== null) {
                bot = Bot;
                isBotAvailable = true;
                Log.i("[BOT] 표준 Bot 객체 사용");
            } else if (typeof BotAPI !== 'undefined' && BotAPI !== null) {
                bot = BotAPI;
                isBotAvailable = true;
                Log.i("[BOT] BotAPI 객체 사용");
            } else {
                // 폴백: 더미 객체 생성
                bot = {
                    send: function(room, message) {
                        Log.w("[BOT] Bot 객체 없음 - 메시지 전송 불가: " + room + " - " + message);
                    },
                    reply: function(room, message) {
                        Log.w("[BOT] Bot 객체 없음 - 답장 전송 불가: " + room + " - " + message);
                    }
                };
                isBotAvailable = false;
                Log.w("[BOT] Bot 객체를 찾을 수 없습니다. 더미 객체 사용");
            }
        } catch (e) {
            Log.e("[BOT] 초기화 실패: " + e);
            bot = {
                send: function() {},
                reply: function() {}
            };
            isBotAvailable = false;
        }
    }

    // Bot 초기화
    _initializeBot();

    return {
        getCurrentBot: function() { return bot; },
        isBotAvailable: function() { return isBotAvailable; }
    };
})();

// =============================================================================
// 8. 메인 모듈 (MainModule) - 컴파일 지연 방지
// =============================================================================
var MainModule = (function() {
    function initializeEventListeners() {
        setTimeout(function() {
            Log.i("[MAIN] 이벤트 리스너 초기화 시작");
            BotCore.initializeEventListeners();
        }, BOT_CONFIG.INITIALIZATION_DELAY);
    }


    return {
        initializeEventListeners: initializeEventListeners
    };
})();

// =============================================================================
// 9. MessengerBotR 표준 이벤트 핸들러
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
        
        // v3.3.0: message 이벤트로 전송
        var messageData = {
            room: room,
            text: Utils.sanitizeText(msg),
            sender: Utils.sanitizeText(sender),
            isGroupChat: isGroupChat,
            channelId: channelId ? channelId.toString() : null,
            logId: threadId ? threadId.toString() : Utils.generateUniqueId(),
            botName: BOT_CONFIG.BOT_NAME,
            packageName: packageName,
            threadId: threadId,
            userHash: sender ? Utils.generateUniqueId() : null,
            isMention: false
        };
        
        BotCore.sendMessage(messageData);
        
        
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
Log.i("[CONFIG] 프로토콜 버전: " + BOT_CONFIG.PROTOCOL_VERSION);
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

Log.i("[SYSTEM] 초기화 완료. v3.3.0 통합 메시지 프로토콜 적용됨.");