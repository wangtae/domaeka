/**
 * MessengerBotR 클라이언트
 * 
 * @description
 * 카카오톡과 서버 간의 통신을 중개하는 브릿지 클라이언트 스크립트입니다.
 * 
 * @compatibility MessengerBotR v0.7.38a ~ v0.7.39a
 * @engine Rhino JavaScript Engine
 * 
 * @requirements
 * [필수 권한]
 * • 메신저봇 설치시 요청 권한들 (폴더는 루트에 'msgbot'으로 [생성]한 다음 "선택"해야 합니다, 전체 경로: "/storage/emulated/0/msgbot")
 * • 배터리 사용 무제한 (MessengerBotR, KakaoTalk)
 * 
 * [권장 사항]
 * • 화면을 항상 켜 놓거나 노래 볼륨을 0으로 해서 무한 재생시키는 등의 처리로 폰이 수면상태로 빠지지 않도록 해야 메시지가 즉시 응답이 됩니다.
 * 
 * [미디어 전송 기능 관련 필요 권한]
 * • MessengerBotR : 다른 앱 위에 표시
 * • KakaoTalk : 사진 및 동영상 엑세스 권한
 * 
 * @restrictions
 * [개발 제약사항]
 * • const 키워드 사용 금지 (Rhino 엔진 제약)
 * • let 키워드 사용 금지 (Rhino 엔진 제약)
 * • 'Code has no side effects' 경고는 정상 동작 (무시 가능)
 * 
 * @version 2.9.0c
 * @author kkobot.com
 * @improvements 연결 안정성 개선, 모듈화, 멀티 서버 지원, 통합 미디어 전송, 설정값 통합
 */

var bot = BotManager.getCurrentBot();

var VERSION = '2.9.0c';

// =======================
// 통합 설정 구성
// =======================

var config = {
    // 봇 기본 설정
    botName: 'LOA.i',
    
    // 서버 연결 설정
    servers: [
        { host: "100.69.44.56", port: 1482, priority: 2, name: "Dev.PC 2" },
        { host: "100.73.137.47", port: 1481, priority: 3, name: "Dev.Laptop 1" },
        { host: "100.73.137.47", port: 1482, priority: 4, name: "Dev.Laptop 2" },
        { host: "100.69.44.56", port: 1481, priority: 5, name: "Dev.PC 1" }
    ],
    currentServerIndex: 0,
    
    // 네트워크 타임아웃 설정
    network: {
        connectTimeout: 5000,        // 서버 연결 타임아웃 (밀리초)
        socketTimeout: 0,            // 소켓 읽기 타임아웃 (0 = 무제한)
        httpConnectTimeout: 30000,   // HTTP 연결 타임아웃 (밀리초)
        httpReadTimeout: 30000,      // HTTP 읽기 타임아웃 (밀리초)
        reconnectInterval: 5000,     // 재연결 간격 (밀리초)
        maxReconnectDelay: 60000,    // 최대 재연결 지연 (밀리초)
        reconnectDelayIncrement: 2000 // 재연결 지연 증가 시간 (밀리초)
    },
    
    // 미디어 파일 경로 설정
    paths: {
        mediaDir: "/storage/emulated/0/msgbot/server-media"
    },
    
    // 미디어 파일명 설정
    fileNaming: {
        mediaPrefix: "media_",
        defaultExtension: "bin"
    },
    
    // 미디어 처리 설정
    media: {
        bufferSize: 8192,                    // 파일 읽기/쓰기 버퍼 크기
        baseWaitTime: 1500,                  // 기본 대기 시간 (밀리초)
        waitTimePerMB: 2000,                 // 1MB당 추가 대기 시간 (밀리초)
        maxWaitTime: 6000,                   // 최대 대기 시간 (밀리초)
        defaultWaitTime: 4000,               // 기본 대기 시간 (오류 시)
        homeTransitionDelay: 1000,           // 홈 화면 전환 지연 (밀리초)
        compilationDelay: 1000               // 컴파일 완료 후 연결 지연 (밀리초)
    },
    
    // 메시지 설정
    message: {
        maxLength: 65000,                    // 최대 메시지 길이
        mediaSeparator: "|||"                // 미디어 구분자
    },
    
    // 패키지 설정
    packages: {
        kakaoTalk: "com.kakao.talk",
        messengerBotR: "com.xfl.msgbot",
        fileProviderSuffix: ".fileprovider"
    },
    
    // MIME 타입 매핑
    mimeTypes: {
        "jpg": "image/jpeg", "jpeg": "image/jpeg", "png": "image/png", "gif": "image/gif",
        "mp4": "video/mp4", "mov": "video/quicktime", "avi": "video/x-msvideo",
        "mp3": "audio/mpeg", "wav": "audio/wav", "m4a": "audio/mp4",
        "pdf": "application/pdf", "txt": "text/plain", "doc": "application/msword",
        "zip": "application/zip"
    },
    
    // 보안 설정 (kkobot.com에서 자동 생성되는 봇별 보안키)
    security: {
        secretKey: "8vQw!@#4kLz9^&*1pXyZ2$%6sDq7!@#8vQw!@#4kLz9^&*1pXyZ2$%6sDq7!@#8",
        botSpecificSalt: "7f$kLz9^&*1pXyZ2"
    }, 

    // 로그 설정
    log: {
        tcpMessages: true // TCP 메시지 관련 로그 출력 여부 (기본값: true)
    }
};

// 전역 변수
var socket = null;
var outputStream = null;
var receiveThread = null;
var reconnectTimeout = null;
var currentRooms = {};

// Packages 참조 지연 로딩으로 컴파일 최적화
var INTENT, URI, FILE, LONG, INTEGER, PackageManager, MediaScannerConnection, ArrayList, URL;

function initializePackages() {
    if (!INTENT) {
        INTENT = Packages.android.content.Intent;
        URI = Packages.android.net.Uri;
        FILE = Packages.java.io.File;
        LONG = Packages.java.lang.Long;
        INTEGER = Packages.java.lang.Integer;
        PackageManager = App.getContext().getPackageManager();
        MediaScannerConnection = Packages.android.media.MediaScannerConnection;
        ArrayList = Packages.java.util.ArrayList;
        URL = Packages.java.net.URL;
    }
}

// =======================
// 유틸리티 모듈
// =======================

var Utils = {
    formatDuration: function(ms) {
        var minutes = Math.floor(ms / 60000);
        var hours = Math.floor(minutes / 60);
        var remainMinutes = minutes % 60;

        if (hours >= 1) {
            return hours + "시간 " + remainMinutes + "분";
        } else {
            return minutes + "분";
        }
    },

    formatTimestamp: function(dateObj) {
        if (!(dateObj instanceof Date)) return '';
        var yyyy = dateObj.getFullYear();
        var mm = ('0' + (dateObj.getMonth() + 1)).slice(-2);
        var dd = ('0' + dateObj.getDate()).slice(-2);
        var hh = ('0' + dateObj.getHours()).slice(-2);
        var mi = ('0' + dateObj.getMinutes()).slice(-2);
        var ss = ('0' + dateObj.getSeconds()).slice(-2);
        return yyyy + "-" + mm + "-" + dd + " " + hh + ":" + mi + ":" + ss;
    },

    formatTimestampWithMs: function(dateObj) {
        if (!(dateObj instanceof Date)) return '';
        var yyyy = dateObj.getFullYear();
        var mm = ('0' + (dateObj.getMonth() + 1)).slice(-2);
        var dd = ('0' + dateObj.getDate()).slice(-2);
        var hh = ('0' + dateObj.getHours()).slice(-2);
        var mi = ('0' + dateObj.getMinutes()).slice(-2);
        var ss = ('0' + dateObj.getSeconds()).slice(-2);
        var ms = ('00' + dateObj.getMilliseconds()).slice(-3);
        return yyyy + "-" + mm + "-" + dd + " " + hh + ":" + mi + ":" + ss + "." + ms;
    },

    sanitizeText: function(text) {
        if (!text) return '';
        if (text.length > config.message.maxLength) {
            Log.e("[VALIDATION] 메시지 길이 초과! → 잘림");
            text = text.substring(0, config.message.maxLength);
        }
        text = text.replace(/[\u0000-\u0009\u000B\u000C\u000E-\u001F\u007F]/g, '');
        text = text.replace(/[\u202A-\u202E\u2066-\u2069]/g, '');
        return text;
    },

    getCpuUsage: function() {
        // 라이노 JS에서 직접 CPU 사용률을 구하는 것은 불가
        // 추후 확장 가능성 고려하여 기본값 반환
        return null;
    },

    getRamUsage: function() {
        try {
            var Runtime = java.lang.Runtime.getRuntime();
            var used = Runtime.totalMemory() - Runtime.freeMemory();
            var max = Runtime.maxMemory();
            return {
                used: used,
                max: max,
                usedMB: Math.round(used / 1048576),
                maxMB: Math.round(max / 1048576)
            };
        } catch (e) {
            return null;
        }
    },

    getTemperature: function() {
        // 라이노 JS에서 직접 온도 정보는 구하기 어려움
        // 추후 확장 가능성 고려하여 기본값 반환
        return null;
    },

    generateUniqueId: function() {
        return Math.random().toString(36).substring(2, 15);
    }
};

// =======================
// 인증 모듈
// =======================

var AuthModule = {
    getDeviceUUID: function() {
        try {
            return Device.getAndroidId();
        } catch (e) {
            return "unknown";
        }
    },

    getMacAddress: function() {
        try {
            var wifiManager = Api.getContext().getSystemService(android.content.Context.WIFI_SERVICE);
            var wifiInfo = wifiManager.getConnectionInfo();
            return wifiInfo.getMacAddress();
        } catch (e) {
            return "unknown";
        }
    },

    getLocalIP: function() {
        try {
            if (socket !== null && socket.isConnected()) {
                return socket.getLocalAddress().getHostAddress();
            }
        } catch (e) {}
        return "unknown";
    },

    generateHMAC: function(data, key) {
        try {
            var Mac = javax.crypto.Mac.getInstance("HmacSHA256");
            var secretKeySpec = new javax.crypto.spec.SecretKeySpec(
                new java.lang.String(key).getBytes("UTF-8"),
                "HmacSHA256"
            );
            Mac.init(secretKeySpec);
            var bytes = Mac.doFinal(new java.lang.String(data).getBytes("UTF-8"));
            var result = [];
            for (var i = 0; i < bytes.length; i++) {
                result.push(("0" + (bytes[i] & 0xFF).toString(16)).slice(-2));
            }
            return result.join("");
        } catch (e) {
            Log.e("HMAC 생성 실패: " + e);
            return "";
        }
    },

    generateSignature: function(data) {
        var signString = [
            "MessengerBotR",
            data.botName,
            data.deviceUUID,
            data.macAddress,
            data.ipAddress,
            data.timestamp,
            config.security.botSpecificSalt
        ].join('|');
        return this.generateHMAC(signString, config.security.secretKey);
    },

    createAuthData: function() {
        var auth = {
            clientType: "MessengerBotR",
            botName: config.botName,
            deviceUUID: this.getDeviceUUID(),
            macAddress: this.getMacAddress(),
            ipAddress: this.getLocalIP(),
            timestamp: Date.now(),
            version: VERSION
        };
        auth.signature = this.generateSignature(auth);
        return auth;
    }
};

// =======================
// 통합 미디어 전송 모듈
// =======================

var MediaModule = {
    extractFileExtension: function(filePath) {
        var lastDot = filePath.lastIndexOf(".");
        if (lastDot !== -1 && lastDot < filePath.length - 1) {
            return filePath.substring(lastDot + 1).toLowerCase();
        }
        return config.fileNaming.defaultExtension;
    },

    determineMimeType: function(extension) {
        return config.mimeTypes[extension] || "application/octet-stream";
    },

    disableStrictMode: function() {
        try {
            var StrictMode = Packages.android.os.StrictMode;
            var builder = new StrictMode.VmPolicy.Builder();
            StrictMode.setVmPolicy(builder.build());
            Log.i("[MEDIA] StrictMode 정책 설정 완료");
        } catch (e) {
            Log.w("[MEDIA] StrictMode 설정 실패: " + e);
        }
    },

    downloadFromUrl: function(url, targetPath) {
        var inputStream = null;
        var outputStream = null;
        try {
            var connection = new URL(url).openConnection();
            connection.setConnectTimeout(config.network.httpConnectTimeout);
            connection.setReadTimeout(config.network.httpReadTimeout);
            
            inputStream = connection.getInputStream();
            outputStream = new java.io.FileOutputStream(new FILE(targetPath));
            
            var buffer = java.lang.reflect.Array.newInstance(java.lang.Byte.TYPE, config.media.bufferSize);
            var bytesRead;
            while ((bytesRead = inputStream.read(buffer)) !== -1) {
                outputStream.write(buffer, 0, bytesRead);
            }
            return true;
        } catch (e) {
            Log.e("[DOWNLOAD] 다운로드 실패: " + e);
            return false;
        } finally {
            if (outputStream) try { outputStream.close(); } catch (e) {}
            if (inputStream) try { inputStream.close(); } catch (e) {}
        }
    },

    createFileProviderUri: function(filePath) {
        try {
            var context = App.getContext();
            var file = new FILE(filePath);
            var authority = context.getPackageName() + config.packages.fileProviderSuffix;
            var FileProvider = Packages.androidx.core.content.FileProvider;
            return FileProvider.getUriForFile(context, authority, file);
        } catch (e) {
            Log.w("[MEDIA] FileProvider URI 생성 실패, 기본 URI 사용: " + e);
            return URI.fromFile(new FILE(filePath));
        }
    },

    saveBase64ToFile: function(base64Data, filePath) {
        try {
            var Base64 = android.util.Base64;
            var bytes = Base64.decode(base64Data, Base64.DEFAULT);
            var fos = new java.io.FileOutputStream(new FILE(filePath));
            fos.write(bytes);
            fos.close();
            
            // 미디어 스캔 등록 (이미지 호환성)
            try {
                MediaScannerConnection.scanFile(
                    App.getContext(),
                    [filePath],
                    [this.determineMimeType(this.extractFileExtension(filePath))],
                    function(path) {
                        Log.i("[MEDIA] 미디어 스캔 완료: " + path);
                    }
                );
            } catch (scanErr) {
                Log.e("[MEDIA] 미디어 스캔 오류: " + scanErr);
            }
            
            return true;
        } catch (e) {
            Log.e("[MEDIA] Base64 파일 저장 실패: " + e);
            return false;
        }
    },

    prepareMediaFile: function(mediaSource, mediaDir, index) {
        var fileName = config.fileNaming.mediaPrefix + java.lang.System.nanoTime() + "_" + index;
        var targetPath = mediaDir + "/" + fileName;
        var downloaded = false;
        
        try {
            var dirFile = new FILE(mediaDir);
            if (!dirFile.exists()) {
                dirFile.mkdirs();
            }
            
            if (mediaSource.startsWith("http://") || mediaSource.startsWith("https://")) {
                var urlObj = new URL(mediaSource);
                var urlPath = urlObj.getPath();
                var extension = this.extractFileExtension(urlPath);
                targetPath += "." + extension;
                
                if (this.downloadFromUrl(mediaSource, targetPath)) {
                    downloaded = true;
                } else {
                    return null;
                }
            } else if (mediaSource.startsWith("data:")) {
                var commaIndex = mediaSource.indexOf(",");
                if (commaIndex !== -1) {
                    var mimeType = mediaSource.substring(5, mediaSource.indexOf(";"));
                    var extension = this.getExtensionFromMimeType(mimeType);
                    targetPath += "." + extension;
                    
                    var base64Data = mediaSource.substring(commaIndex + 1);
                    if (this.saveBase64ToFile(base64Data, targetPath)) {
                        downloaded = true;
                    } else {
                        return null;
                    }
                }
            } else {
                // 기존 파일 경로 또는 Base64 문자열 처리 (레거시 호환성)
                var sourceFile = new FILE(mediaSource);
                if (sourceFile.exists()) {
                    var extension = this.extractFileExtension(mediaSource);
                    targetPath += "." + extension;
                    
                    var inputStream = new java.io.FileInputStream(sourceFile);
                    var outputStream = new java.io.FileOutputStream(new FILE(targetPath));
                    
                    var buffer = java.lang.reflect.Array.newInstance(java.lang.Byte.TYPE, config.media.bufferSize);
                    var bytesRead;
                    while ((bytesRead = inputStream.read(buffer)) !== -1) {
                        outputStream.write(buffer, 0, bytesRead);
                    }
                    
                    inputStream.close();
                    outputStream.close();
                    downloaded = true;
                } else {
                    // Base64 문자열로 간주 (레거시 IMAGE_BASE64 호환성)
                    targetPath += ".png"; // 기본 확장자
                    if (this.saveBase64ToFile(mediaSource, targetPath)) {
                        downloaded = true;
                    } else {
                        return null;
                    }
                }
            }
            
            var extension = this.extractFileExtension(targetPath);
            var mimeType = this.determineMimeType(extension);
            
            return {
                path: targetPath,
                mimeType: mimeType,
                downloaded: downloaded
            };
        } catch (e) {
            Log.e("[MEDIA] 파일 준비 실패: " + e);
            return null;
        }
    },

    getExtensionFromMimeType: function(mimeType) {
        var mimeToExt = {
            "image/jpeg": "jpg",
            "image/png": "png",
            "image/gif": "gif",
            "video/mp4": "mp4",
            "audio/mpeg": "mp3",
            "application/pdf": "pdf"
        };
        return mimeToExt[mimeType] || config.fileNaming.defaultExtension;
    },

    sendMedia: function(channelId, mediaSources, mimeType) {
        try {
            initializePackages();
            this.disableStrictMode();
            
            var processedFiles = [];
            var downloadedFiles = [];
            
            var sources = Array.isArray(mediaSources) ? mediaSources : [mediaSources];
            
            for (var i = 0; i < sources.length; i++) {
                var mediaInfo = this.prepareMediaFile(sources[i], config.paths.mediaDir, i);
                if (mediaInfo) {
                    processedFiles.push(mediaInfo);
                    if (mediaInfo.downloaded) {
                        downloadedFiles.push(mediaInfo.path);
                    }
                }
            }
            
            if (processedFiles.length === 0) {
                Log.e("[MEDIA] 처리할 파일이 없습니다");
                return false;
            }
            
            var context = App.getContext();
            var uris = new ArrayList();
            
            for (var j = 0; j < processedFiles.length; j++) {
                var fileInfo = processedFiles[j];
                var uri = this.createFileProviderUri(fileInfo.path);
                uris.add(uri);
            }
            
            var intent = new INTENT(INTENT.ACTION_SEND_MULTIPLE);
            intent.setPackage(config.packages.kakaoTalk);
            
            // MIME 타입 설정 (레거시 호환성을 위해 mimeType 매개변수 지원)
            var intentMimeType = mimeType || processedFiles[0].mimeType || "*/*";
            intent.setType(intentMimeType);
            intent.putParcelableArrayListExtra(INTENT.EXTRA_STREAM, uris);
            
            var channelIdLong = new LONG(channelId.toString());
            intent.putExtra("key_id", channelIdLong);
            intent.putExtra("key_type", new INTEGER(1));
            intent.putExtra("key_from_direct_share", true);
            intent.addFlags(INTENT.FLAG_ACTIVITY_NEW_TASK | INTENT.FLAG_ACTIVITY_CLEAR_TOP);
            intent.addFlags(INTENT.FLAG_GRANT_READ_URI_PERMISSION);
            
            context.startActivity(intent);
            
            var waitTime = this.calculateWaitTime(processedFiles[0].path);
            java.lang.Thread.sleep(waitTime);
            
            this.goHome();
            java.lang.Thread.sleep(config.media.homeTransitionDelay);
            
            for (var k = 0; k < downloadedFiles.length; k++) {
                this.deleteFile(downloadedFiles[k]);
            }
            
            return true;
        } catch (e) {
            Log.e("[MEDIA] 전송 실패: " + e);
            return false;
        }
    },

    calculateWaitTime: function(filePath) {
        try {
            var file = new FILE(filePath);
            if (!file.exists()) {
                return config.media.defaultWaitTime;
            }
            
            var fileSize = file.length();
            var waitTime = config.media.baseWaitTime + (fileSize / 1048576) * config.media.waitTimePerMB;
            return Math.min(Math.round(waitTime), config.media.maxWaitTime);
        } catch (e) {
            Log.e("[MEDIA] 대기시간 계산 오류: " + e);
            return config.media.defaultWaitTime;
        }
    },

    deleteFile: function(filePath) {
        try {
            var file = new FILE(filePath);
            if (file.exists()) {
                file.delete();
                Log.i("[MEDIA] 파일 삭제: " + filePath);
            }
        } catch (e) {
            Log.e("[MEDIA] 파일 삭제 오류: " + e);
        }
    },

    goHome: function() {
        try {
            var context = App.getContext();
            var packageManager = context.getPackageManager();
            
            try {
                var msgbotIntent = packageManager.getLaunchIntentForPackage(config.packages.messengerBotR);
                if (msgbotIntent) {
                    msgbotIntent.addFlags(INTENT.FLAG_ACTIVITY_NEW_TASK | INTENT.FLAG_ACTIVITY_CLEAR_TOP);
                    context.startActivity(msgbotIntent);
                    return;
                }
            } catch (botErr) {
                Log.e("[MEDIA] 메신저봇R로 이동 실패: " + botErr);
            }
            
            var home = new INTENT(INTENT.ACTION_MAIN);
            home.addCategory(INTENT.CATEGORY_HOME);
            home.setFlags(INTENT.FLAG_ACTIVITY_NEW_TASK | INTENT.FLAG_ACTIVITY_CLEAR_TOP);
            context.startActivity(home);
        } catch (e) {
            Log.e("[MEDIA] 화면 이동 오류: " + e);
        }
    }
};

// =======================
// 네트워크 연결 모듈
// =======================

var NetworkModule = {
    reconnectAttempts: 0,

    connectSocket: function() {
        try {
            this.closeSocket();

            // 모든 서버를 순차적으로 시도
            for (var i = 0; i < config.servers.length; i++) {
                var serverInfo = config.servers[config.currentServerIndex];
                
                Log.i("[TCP] 연결 시도: " + serverInfo.name + " (" + serverInfo.host + ":" + serverInfo.port + ")");
                
                var address = new java.net.InetSocketAddress(serverInfo.host, serverInfo.port);
                socket = new java.net.Socket();

                try {
                    socket.connect(address, config.network.connectTimeout);
                    socket.setSoTimeout(config.network.socketTimeout);
                    
                    outputStream = new java.io.BufferedWriter(
                        new java.io.OutputStreamWriter(socket.getOutputStream(), "UTF-8")
                    );

                    Log.i("[TCP] 서버 연결 완료: " + serverInfo.name);

                    var handshake = JSON.stringify({
                        botName: config.botName,
                        version: VERSION
                    }) + "\n";

                    outputStream.write(handshake);
                    outputStream.flush();

                    this.startReceiveThread();
                    return; // 연결 성공하면 함수 종료

                } catch (connectErr) {
                    Log.e("[TCP] 서버 연결 실패: " + serverInfo.name + " - " + connectErr);
                    try {
                        if (socket !== null) socket.close();
                    } catch (e) {}
                }
                
                // 다음 서버로 이동
                config.currentServerIndex = (config.currentServerIndex + 1) % config.servers.length;
            }
            
            // 모든 서버 연결 실패 시 재시도
            Log.e("[TCP] 모든 서버 연결 실패, 재시도 예약");
            this.scheduleReconnect();

        } catch (e) {
            Log.e("[TCP] 연결 오류: " + e);
            this.scheduleReconnect();
        }
    },

    closeSocket: function() {
        try {
            if (receiveThread && receiveThread.isAlive()) {
                receiveThread.interrupt();
                receiveThread = null;
            }

            if (outputStream !== null) {
                outputStream.close();
                outputStream = null;
            }

            if (socket !== null && !socket.isClosed()) {
                socket.shutdownInput();
                socket.shutdownOutput();
                socket.close();
                socket = null;
            }

        } catch (e) {
            Log.e("[TCP] closeSocket 오류: " + e);
        }
    },

    scheduleReconnect: function() {
        if (reconnectTimeout) return;

        this.reconnectAttempts++;
        var delay = Math.min(
            config.network.reconnectInterval * Math.pow(2, this.reconnectAttempts),
            config.network.maxReconnectDelay
        );

        Log.i("[TCP] 재연결 시도 예정 (" + this.reconnectAttempts + "회차, " + delay + "ms 후)");

        var self = this;
        reconnectTimeout = setTimeout(function() {
            reconnectTimeout = null;
            self.connectSocket();
        }, delay + config.network.reconnectDelayIncrement);
    },

    sendMessageToServer: function(event, data) {
        if (!socket || socket.isClosed() || !outputStream) {
            Log.e('[TCP] 소켓 연결 안됨 → 전송 실패');
            return;
        }
        // 인증 정보 추가
        data.auth = AuthModule.createAuthData();

        var packet = { event: event, data: data };
        var jsonStr = JSON.stringify(packet) + "\n";

        try {
            outputStream.write(jsonStr);
            outputStream.flush();
            if ( config.log.tcpMessages == true ) {
                Log.i("[TCP] 전송 완료: " + jsonStr);
            }
            
        } catch (e) {
            Log.e("[TCP] 전송 실패: " + e);
            this.scheduleReconnect();
        }
    },

    startReceiveThread: function() {
        if (receiveThread && receiveThread.isAlive()) {
            Log.i("[TCP] 수신 스레드 이미 실행 중");
            return;
        }

        var self = this;
        receiveThread = new java.lang.Thread({
            run: function() {
                var inputStream = null;

                try {
                    inputStream = new java.io.BufferedReader(
                        new java.io.InputStreamReader(socket.getInputStream(), "UTF-8")
                    );
                    Log.i("[TCP] 수신 스레드 시작");

                    while (!java.lang.Thread.interrupted() && socket !== null && !socket.isClosed()) {
                        var line = inputStream.readLine();

                        if (line === null) {
                            Log.e("[TCP] 서버 연결 끊김 (line == null)");
                            throw "서버 연결 종료 감지";
                        }

                        Log.i("[TCP] 서버 응답 수신: " + line);
                        MessageModule.handleServerResponse(line);
                    }

                } catch (err) {
                    Log.e("[TCP] 수신 스레드 오류: " + err);

                } finally {
                    if (inputStream !== null) {
                        inputStream.close();
                    }

                    Log.i("[TCP] 수신 스레드 종료");
                    self.scheduleReconnect();
                }
            }
        });

        try {
            receiveThread.start();
        } catch (startErr) {
            Log.e("[TCP] 수신 스레드 시작 실패: " + startErr);
        }
    }
};

// =======================
// 메시지 처리 모듈
// =======================

var MessageModule = {
    onMessage: function(msg) {
        Log.i("[EVENT] MESSAGE 수신 → " + msg.room + " / " + msg.content);

        // channelId 매핑 등록
        if (msg.channelId) {
            var channelIdStr = msg.channelId.toString();
            if (currentRooms[channelIdStr] != msg.room) {
                currentRooms[channelIdStr] = msg.room;
                Log.i("[매핑] channelId 등록: " + channelIdStr + " → " + msg.room);
            }
        }

        var sanitizedContent = Utils.sanitizeText(msg.content);
        if (!sanitizedContent || sanitizedContent.length === 0) {
            Log.e("[MESSAGE] 필터링 후 메시지가 비어 있음 → 처리 중단");
            return;
        }

        // 일반 메시지 처리
        var timestamp = Utils.formatTimestamp(new Date());

        // analyze 이벤트 전송
        NetworkModule.sendMessageToServer('analyze', {
            room: msg.room,
            text: sanitizedContent,
            sender: Utils.sanitizeText(msg.author.name),
            isGroupChat: msg.isGroupChat,
            channelId: msg.channelId ? msg.channelId.toString() : null,
            logId: msg.logId ? msg.logId.toString() : null,
            userHash: msg.author.hash,
            isMention: !!msg.isMention,
            timestamp: timestamp,
            botName: config.botName,
            clientType: "MessengerBotR",
            auth: AuthModule.createAuthData()
        });
    },

    handleServerResponse: function(rawMsg) {
        try {
            var packet = JSON.parse(rawMsg);
            var event = packet.event;
            var data = packet.data;

            if (!data || !data.room || typeof data.text === 'undefined') {
                Log.e('[TCP] 응답 데이터 오류');
                return;
            }

            // messageResponse 이벤트 처리
            if (event === 'messageResponse') {
                var messageText = data.text;
                var roomName = data.room;
                var channelId = data.channel_id;

                // 미디어 응답 처리 (MEDIA_ 및 IMAGE_BASE64 통합)
                if (messageText.startsWith("MEDIA_") || messageText.startsWith("IMAGE_BASE64:")) {
                    Log.i("[TCP] 미디어 응답 감지");
                    
                    try {
                        var mediaSources = [];
                        var mimeType = null;
                        
                        if (messageText.startsWith("MEDIA_")) {
                            // 새로운 MEDIA_ 프로토콜
                            var mediaData = messageText.substring(6); // "MEDIA_" 제거
                            mediaSources = mediaData.split(config.message.mediaSeparator);
                        } else if (messageText.startsWith("IMAGE_BASE64:")) {
                            // 레거시 IMAGE_BASE64 프로토콜 호환성
                            var base64Payload = messageText.replace("IMAGE_BASE64:", "");
                            mimeType = "image/png";
                            
                            if (base64Payload.startsWith("type=")) {
                                var typeEnd = base64Payload.indexOf(";");
                                if (typeEnd > 5) {
                                    mimeType = base64Payload.substring(5, typeEnd);
                                    base64Payload = base64Payload.substring(typeEnd + 1);
                                }
                            }
                            mediaSources = base64Payload.split(config.message.mediaSeparator);
                        }
                        
                        if (!channelId && roomName) {
                            for (var cid in currentRooms) {
                                if (currentRooms[cid] === roomName) {
                                    channelId = cid;
                                    break;
                                }
                            }
                        }
                        
                        if (channelId) {
                            MediaModule.sendMedia(channelId, mediaSources, mimeType);
                            Log.i("[TCP] 미디어 전송 처리 완료 → " + roomName);
                        } else {
                            Log.e("[TCP] 미디어 전송 실패 → channelId 없음");
                            bot.send(roomName, "미디어 전송에 실패했습니다. (channelId 없음)");
                        }
                    } catch (mediaErr) {
                        Log.e("[TCP] 미디어 처리 오류: " + mediaErr);
                        bot.send(roomName, "미디어 처리 중 오류가 발생했습니다.");
                    }
                    return;
                }

                // 일반 텍스트 메시지 처리
                try {
                    bot.send(roomName, messageText);
                    Log.i("[TCP] messageResponse: 메시지 전송 완료 → " + roomName);
                } catch (e) {
                    Log.e("[TCP] messageResponse: 메시지 전송 실패 → " + e);
                }
                return;
            }

            // ping 이벤트 처리
            if (event === 'ping') {
                var clientStatus = {
                    cpu: Utils.getCpuUsage(),
                    ram: Utils.getRamUsage(),
                    temp: Utils.getTemperature()
                };
                NetworkModule.sendMessageToServer('ping', {
                    bot_name: data.bot_name,
                    channel_id: data.channel_id,
                    room: data.room,
                    user_hash: data.user_hash,
                    server_timestamp: data.server_timestamp,
                    client_status: clientStatus,
                    is_manual: false
                });
                return;
            }

        } catch (e) {
            Log.e("[TCP] 응답 처리 실패: " + e);
        }
    }
};

// =======================
// 이벤트 핸들러 모듈
// =======================

var EventModule = {
    onStop: function() {
        Log.i("[메신저봇] 중단 감지, 소켓 닫기");
        NetworkModule.closeSocket();
    },

    onDestroy: function() {
        Log.i("[메신저봇] 종료 감지, onStop 호출");
        this.onStop();
    },

    onStartCompile: function() {
        Log.i("[이벤트] startCompile 감지, 소켓 및 스레드 정리 시작");
        NetworkModule.closeSocket();
        if (reconnectTimeout) {
            clearTimeout(reconnectTimeout);
            reconnectTimeout = null;
        }
        Log.i("[이벤트] startCompile 처리 완료");
    }
};

// =======================
// 메인 실행 모듈
// =======================

var MainModule = {
    startBot: function() {
        Log.i("[시작] 메신저봇R TCP 클라이언트 시작");
        Log.i("[버전] 현재 실행 중인 클라이언트 버전: " + VERSION);
        
        // 서버 목록 로그 출력
        Log.i("[설정] 서버 목록 (priority 순서):");
        for (var i = 0; i < config.servers.length; i++) {
            var server = config.servers[i];
            Log.i("[설정] " + (i + 1) + ". " + server.name + " (priority: " + server.priority + ")");
        }

        bot.addListener(Event.MESSAGE, MessageModule.onMessage);
        
        // 연결을 설정된 시간만큼 지연 (컴파일 완료 후 연결) - 컴파일 최적화
        setTimeout(function() {
            NetworkModule.connectSocket();
        }, config.media.compilationDelay);

        Log.i("[시작] 메신저봇R TCP 클라이언트 시작 완료");
    },

    initializeEventListeners: function() {
        bot.addListener(Event.Activity.STOP, EventModule.onStop);
        bot.addListener(Event.Activity.DESTROY, EventModule.onDestroy);
        bot.addListener(Event.START_COMPILE, EventModule.onStartCompile);
    }
};

// =======================
// 초기화 및 실행
// =======================

MainModule.initializeEventListeners();
MainModule.startBot();