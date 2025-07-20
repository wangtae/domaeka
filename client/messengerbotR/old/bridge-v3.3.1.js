/**
 * MessengerBotR Bridge v3.3.1 - Development Optimized
 * 
 * @description 카카오톡과 서버 간의 통신을 중개하는 브릿지 클라이언트
 * @version 3.3.1
 * @author kkobot.com
 * 
 * 개발 단계 최적화 버전 - 가독성 유지하면서 크기 축소
 * - 핵심 주석 유지
 * - 의미있는 변수명 사용
 * - 총 크기: 50,000자 미만
 */

// =============================================================================
// Polyfills
// =============================================================================
if (!Array.isArray) {
    Array.isArray = function(arg) {
        return Object.prototype.toString.call(arg) === '[object Array]';
    };
}

if (!String.prototype.startsWith) {
    String.prototype.startsWith = function(searchString, position) {
        position = position || 0;
        return this.substring(position, position + searchString.length) === searchString;
    };
}

if (!String.prototype.endsWith) {
    String.prototype.endsWith = function(searchString, position) {
        var subjectString = this.toString();
        if (typeof position !== 'number' || !isFinite(position) || Math.floor(position) !== position || position > subjectString.length) {
            position = subjectString.length;
        }
        position -= searchString.length;
        var lastIndex = subjectString.lastIndexOf(searchString, position);
        return lastIndex !== -1 && lastIndex === position;
    };
}

if (!String.prototype.includes) {
    String.prototype.includes = function(search, start) {
        if (typeof start !== 'number') start = 0;
        return start + search.length > this.length ? false : this.indexOf(search, start) !== -1;
    };
}

// =============================================================================
// 설정
// =============================================================================
var BOT_CONFIG = {
    VERSION: '3.3.1',
    BOT_NAME: 'LOA.i',
    CLIENT_TYPE: 'MessengerBotR',
    
    SECRET_KEY: "8vQw!@#4kLz9^&*1pXyZ2$%6sDq7!@#8vQw!@#4kLz9^&*1pXyZ2$%6sDq7!@#8",
    BOT_SPECIFIC_SALT: "7f$kLz9^&*1pXyZ2",
    
    SERVER_LIST: [
        { host: "100.69.44.56", port: 1485, priority: 2, name: "Dev.PC 2" },
        { host: "100.73.137.47", port: 1485, priority: 3, name: "Dev.Laptop 1" },
        { host: "100.73.137.47", port: 1486, priority: 4, name: "Dev.Laptop 2" },
        { host: "100.69.44.56", port: 1486, priority: 5, name: "Dev.PC 1" }
    ],
    
    MAX_MESSAGE_LENGTH: 65000,
    BASE_RECONNECT_DELAY: 2000,
    MAX_RECONNECT_DELAY: 60000,
    MAX_RECONNECT_ATTEMPTS: -1,
    MESSAGE_TTL: 30000,
    
    MEDIA_TEMP_DIR: "/storage/emulated/0/msgbot/server-media",
    FILE_PROVIDER_AUTHORITY: "com.xfl.msgbot.provider",
    KAKAOTALK_PACKAGE_NAME: "com.kakao.talk",
    
    INITIALIZATION_DELAY: 300,
    ROOM_INACTIVE_DAYS: 30,
    TEMP_FILE_DAYS: 7,
    CLEANUP_INTERVAL: 86400000,
    
    QUEUE_SIZE: 2000,
    TIMEOUT_RECEIVE: 5000,
    MEDIA_ENABLED: true,
    
    HEARTBEAT_INTERVAL: 30000,
    RECONNECT_INTERVALS: [1000, 2000, 5000, 10000, 30000, 60000],
    
    LOG_LEVEL: 'INFO'
};

// =============================================================================
// 로거
// =============================================================================
var BridgeLogger = (function() {
    var LEVELS = { DEBUG: 0, INFO: 1, WARN: 2, ERROR: 3 };
    var currentLevel = LEVELS.INFO;
    
    function getTimestamp() {
        var d = new Date();
        var h = ('0' + d.getHours()).slice(-2);
        var m = ('0' + d.getMinutes()).slice(-2);
        var s = ('0' + d.getSeconds()).slice(-2);
        return h + ':' + m + ':' + s;
    }
    
    function log(level, context, message) {
        if (level >= currentLevel) {
            var levelName = Object.keys(LEVELS).find(function(k) { return LEVELS[k] === level; });
            var timestamp = getTimestamp();
            var formatted = "[" + levelName + "] " + timestamp + " | " + context + " | " + message;
            
            switch (level) {
                case LEVELS.ERROR: Log.e(formatted); break;
                case LEVELS.WARN: Log.w(formatted); break;
                default: Log.i(formatted);
            }
        }
    }
    
    return {
        debug: function(ctx, msg) { log(LEVELS.DEBUG, ctx, msg); },
        info: function(ctx, msg) { log(LEVELS.INFO, ctx, msg); },
        warn: function(ctx, msg) { log(LEVELS.WARN, ctx, msg); },
        error: function(ctx, msg) { log(LEVELS.ERROR, ctx, msg); },
        setLevel: function(level) {
            currentLevel = typeof level === 'string' ? LEVELS[level.toUpperCase()] || LEVELS.INFO : level;
        }
    };
})();

// =============================================================================
// 유틸리티
// =============================================================================
var Utils = {
    generateUUID: function() {
        try {
            if (typeof Security !== 'undefined' && Security.ulid) {
                return Security.ulid();
            }
        } catch (e) {}
        return Date.now().toString(36) + Math.random().toString(36).substring(2);
    },
    
    sanitizeMessage: function(text) {
        if (!text) return '';
        if (text.length > BOT_CONFIG.MAX_MESSAGE_LENGTH) {
            BridgeLogger.warn("UTILS", "메시지가 너무 깁니다. 자동으로 잘립니다.");
            text = text.substring(0, BOT_CONFIG.MAX_MESSAGE_LENGTH);
        }
        // 제어 문자 제거
        return text.replace(/[\u0000-\u0009\u000B\u000C\u000E-\u001F\u007F]/g, '')
                  .replace(/[\u202A-\u202E\u2066-\u2069]/g, '');
    }
};

// =============================================================================
// 동기화 함수
// =============================================================================
var synchronized = (function() {
    var locks = {};
    var queues = {};
    
    return function(lockId, fn) {
        if (locks[lockId]) {
            if (!queues[lockId]) queues[lockId] = [];
            queues[lockId].push(fn);
            return;
        }
        
        locks[lockId] = true;
        try {
            fn();
        } finally {
            locks[lockId] = false;
            if (queues[lockId] && queues[lockId].length > 0) {
                var nextFn = queues[lockId].shift();
                setTimeout(function() {
                    synchronized(lockId, nextFn);
                }, 0);
            }
        }
    };
})();

// =============================================================================
// 디바이스 정보
// =============================================================================
var DeviceInfo = {
    socket: null,
    
    getAndroidID: function() {
        try {
            return android.provider.Settings.Secure.getString(
                android.app.ActivityThread.currentApplication().getContentResolver(),
                android.provider.Settings.Secure.ANDROID_ID
            );
        } catch (e) {
            BridgeLogger.error("DEVICE", "Android ID 가져오기 실패: " + e);
            return "unknown";
        }
    },
    
    getDeviceIP: function() {
        try {
            if (this.socket && this.socket.isConnected()) {
                return this.socket.getLocalAddress().getHostAddress();
            }
        } catch (e) {
            BridgeLogger.error("DEVICE", "IP 주소 가져오기 실패: " + e);
        }
        return "unknown";
    },
    
    getHandshakeInfo: function() {
        return {
            clientType: BOT_CONFIG.CLIENT_TYPE,
            botName: BOT_CONFIG.BOT_NAME,
            version: BOT_CONFIG.VERSION,
            deviceID: this.getAndroidID(),
            deviceIP: this.getDeviceIP(),
            deviceInfo: this.getDeviceInfo()
        };
    },
    
    getDeviceInfo: function() {
        try {
            var model = android.os.Build.MODEL || "Unknown";
            var brand = android.os.Build.BRAND || "Unknown";
            var version = android.os.Build.VERSION.RELEASE || "Unknown";
            var sdk = android.os.Build.VERSION.SDK_INT || "Unknown";
            return brand + " " + model + " (Android " + version + ", API " + sdk + ")";
        } catch (e) {
            return "Unknown Device";
        }
    }
};

// =============================================================================
// 인증 모듈
// =============================================================================
var Auth = {
    socket: null,
    
    getDeviceUUID: function() {
        try {
            return Device.getAndroidId();
        } catch (e) {
            return "unknown";
        }
    },
    
    getMacAddress: function() {
        try {
            var wifi = Api.getContext().getSystemService(android.content.Context.WIFI_SERVICE);
            return wifi.getConnectionInfo().getMacAddress();
        } catch (e) {
            return "unknown";
        }
    },
    
    generateHMAC: function(data, key) {
        try {
            var Mac = javax.crypto.Mac.getInstance("HmacSHA256");
            var secretKey = new javax.crypto.spec.SecretKeySpec(
                new java.lang.String(key).getBytes("UTF-8"), "HmacSHA256"
            );
            Mac.init(secretKey);
            var hash = Mac.doFinal(new java.lang.String(data).getBytes("UTF-8"));
            
            var result = [];
            for (var i = 0; i < hash.length; i++) {
                result.push(("0" + (hash[i] & 0xFF).toString(16)).slice(-2));
            }
            return result.join("");
        } catch (e) {
            BridgeLogger.error("AUTH", "HMAC 생성 실패: " + e);
            return "";
        }
    },
    
    createAuthPayload: function() {
        var authData = {
            clientType: BOT_CONFIG.CLIENT_TYPE,
            botName: BOT_CONFIG.BOT_NAME,
            deviceUUID: this.getDeviceUUID(),
            deviceID: DeviceInfo.getAndroidID(),
            macAddress: this.getMacAddress(),
            ipAddress: this.socket && this.socket.isConnected() ? 
                       this.socket.getLocalAddress().getHostAddress() : "unknown",
            timestamp: Date.now(),
            version: BOT_CONFIG.VERSION
        };
        
        var signString = [
            BOT_CONFIG.CLIENT_TYPE,
            authData.botName,
            authData.deviceUUID,
            authData.macAddress,
            authData.ipAddress,
            authData.timestamp,
            BOT_CONFIG.BOT_SPECIFIC_SALT
        ].join('|');
        
        authData.signature = this.generateHMAC(signString, BOT_CONFIG.SECRET_KEY);
        return authData;
    }
};

// =============================================================================
// 에러 처리
// =============================================================================
var ErrorHandler = {
    ERROR_TYPES: {
        NETWORK_TIMEOUT: 'NETWORK_TIMEOUT',
        CONNECTION_LOST: 'CONNECTION_LOST',
        CONNECTION_RESET: 'CONNECTION_RESET',
        SERVER_ERROR: 'SERVER_ERROR',
        AUTH_FAILED: 'AUTH_FAILED',
        MESSAGE_ERROR: 'MESSAGE_ERROR',
        UNKNOWN: 'UNKNOWN'
    },
    
    RECOVERY_STRATEGIES: {
        RETRY: 'RETRY',
        RECONNECT: 'RECONNECT',
        IGNORE: 'IGNORE',
        FATAL: 'FATAL'
    },
    
    handle: function(error, context) {
        var errorType = this.classifyError(error);
        var strategy = this.getRecoveryStrategy(errorType);
        
        BridgeLogger.error(context, "Type: " + errorType + " | Strategy: " + strategy + " | Error: " + error);
        
        return {
            type: errorType,
            strategy: strategy,
            error: error
        };
    },
    
    classifyError: function(error) {
        var errorStr = error.toString();
        var errorLower = errorStr.toLowerCase();
        
        // 연결 끊김
        if (errorStr.indexOf("ECONNRESET") !== -1 || 
            errorStr.indexOf("Connection reset") !== -1 ||
            errorStr.indexOf("서버 연결 종료") !== -1 ||
            errorStr.indexOf("연결이 끊어짐") !== -1 ||
            errorStr.indexOf("연결 끊김") !== -1) {
            return this.ERROR_TYPES.CONNECTION_LOST;
        }
        
        // 연결 재설정
        if (errorStr.indexOf("Connection reset by peer") !== -1 ||
            errorStr.indexOf("연결 재설정") !== -1) {
            return this.ERROR_TYPES.CONNECTION_RESET;
        }
        
        // 타임아웃
        if (errorStr.indexOf("SocketTimeout") !== -1 || 
            errorStr.indexOf("timeout") !== -1 ||
            errorStr.indexOf("시간 초과") !== -1 ||
            errorStr.indexOf("타임아웃") !== -1) {
            return this.ERROR_TYPES.NETWORK_TIMEOUT;
        }
        
        // 인증 실패
        if (errorStr.indexOf("AUTH_FAILED") !== -1 ||
            errorStr.indexOf("인증 실패") !== -1 ||
            errorStr.indexOf("인증 거부") !== -1) {
            return this.ERROR_TYPES.AUTH_FAILED;
        }
        
        // 서버 에러
        if (errorStr.indexOf("500") !== -1 || 
            errorStr.indexOf("502") !== -1 ||
            errorStr.indexOf("503") !== -1 ||
            errorStr.indexOf("서버 오류") !== -1) {
            return this.ERROR_TYPES.SERVER_ERROR;
        }
        
        // 메시지 에러
        if (errorStr.indexOf("JSON") !== -1 || 
            errorStr.indexOf("parse") !== -1 ||
            errorStr.indexOf("메시지") !== -1) {
            return this.ERROR_TYPES.MESSAGE_ERROR;
        }
        
        return this.ERROR_TYPES.UNKNOWN;
    },
    
    getRecoveryStrategy: function(errorType) {
        var strategies = {
            NETWORK_TIMEOUT: this.RECOVERY_STRATEGIES.RETRY,
            CONNECTION_LOST: this.RECOVERY_STRATEGIES.RECONNECT,
            CONNECTION_RESET: this.RECOVERY_STRATEGIES.RECONNECT,
            SERVER_ERROR: this.RECOVERY_STRATEGIES.RETRY,
            AUTH_FAILED: this.RECOVERY_STRATEGIES.FATAL,
            MESSAGE_ERROR: this.RECOVERY_STRATEGIES.IGNORE,
            UNKNOWN: this.RECOVERY_STRATEGIES.RECONNECT
        };
        
        return strategies[errorType] || this.RECOVERY_STRATEGIES.IGNORE;
    }
};

// =============================================================================
// 미디어 처리
// =============================================================================
var MediaHandler = {
    PACKAGES: {
        File: Packages.java.io.File,
        FileOutputStream: java.io.FileOutputStream,
        Base64: android.util.Base64,
        MediaScanner: Packages.android.media.MediaScannerConnection,
        FileProvider: Packages.androidx.core.content.FileProvider,
        Uri: Packages.android.net.Uri,
        URL: Packages.java.net.URL
    },
    
    MIME_TYPES: {
        jpg: "image/jpeg", jpeg: "image/jpeg", png: "image/png",
        gif: "image/gif", webp: "image/webp", mp4: "video/mp4",
        mov: "video/quicktime", avi: "video/x-msvideo",
        mp3: "audio/mpeg", wav: "audio/wav", m4a: "audio/mp4"
    },
    
    downloadFile: function(url, targetPath) {
        var input = null;
        var output = null;
        
        try {
            var connection = new this.PACKAGES.URL(url).openConnection();
            connection.setConnectTimeout(30000);
            connection.setReadTimeout(30000);
            
            input = connection.getInputStream();
            output = new this.PACKAGES.FileOutputStream(new this.PACKAGES.File(targetPath));
            
            var buffer = java.lang.reflect.Array.newInstance(java.lang.Byte.TYPE, 8192);
            var bytesRead;
            
            while ((bytesRead = input.read(buffer)) !== -1) {
                output.write(buffer, 0, bytesRead);
            }
            
            return true;
        } catch (e) {
            ErrorHandler.handle(e, "MEDIA_DOWNLOAD");
            return false;
        } finally {
            if (output) try { output.close(); } catch (e) {}
            if (input) try { input.close(); } catch (e) {}
        }
    },
    
    saveBase64Image: function(base64Data, fileName) {
        try {
            var fullPath = BOT_CONFIG.MEDIA_TEMP_DIR + "/" + fileName;
            var dir = new this.PACKAGES.File(BOT_CONFIG.MEDIA_TEMP_DIR);
            
            if (!dir.exists()) {
                dir.mkdirs();
            }
            
            var imageFile = new this.PACKAGES.File(fullPath);
            var decodedBytes = this.PACKAGES.Base64.decode(base64Data, this.PACKAGES.Base64.DEFAULT);
            var fileOutput = new this.PACKAGES.FileOutputStream(imageFile);
            
            fileOutput.write(decodedBytes);
            fileOutput.close();
            
            this.scanMediaFile(fullPath);
            return fullPath;
        } catch (e) {
            ErrorHandler.handle(e, "MEDIA_SAVE_BASE64");
            return null;
        }
    },
    
    scanMediaFile: function(filePath) {
        try {
            var context = App.getContext();
            this.PACKAGES.MediaScanner.scanFile(context, [filePath], null, null);
        } catch (e) {
            BridgeLogger.warn("MEDIA", "미디어 스캔 실패: " + e);
        }
    },
    
    getFileUri: function(filePath) {
        try {
            var file = new this.PACKAGES.File(filePath);
            if (!file.exists()) {
                BridgeLogger.error("MEDIA", "파일이 존재하지 않습니다: " + filePath);
                return null;
            }
            
            try {
                return this.PACKAGES.FileProvider.getUriForFile(
                    App.getContext(),
                    BOT_CONFIG.FILE_PROVIDER_AUTHORITY,
                    file
                );
            } catch (e) {
                return this.PACKAGES.Uri.fromFile(file);
            }
        } catch (e) {
            ErrorHandler.handle(e, "MEDIA_GET_URI");
            return null;
        }
    }
};

// =============================================================================
// 네트워크 관리
// =============================================================================
var Network = {
    socket: null,
    reader: null,
    writer: null,
    currentServer: null,
    reconnectAttempts: 0,
    isConnecting: false,
    isConnected: false,
    receiveThread: null,
    
    connectToServer: function() {
        if (this.isConnecting) {
            BridgeLogger.warn("NETWORK", "이미 연결 시도 중입니다.");
            return;
        }
        
        this.isConnecting = true;
        var serverList = BOT_CONFIG.SERVER_LIST.slice()
            .sort(function(a, b) { return a.priority - b.priority; });
        
        for (var i = 0; i < serverList.length; i++) {
            var server = serverList[i];
            BridgeLogger.info("NETWORK", "서버 연결 시도: " + server.name + " (" + server.host + ":" + server.port + ")");
            
            if (this._attemptConnection(server)) {
                this.currentServer = server;
                this.isConnected = true;
                this.reconnectAttempts = 0;
                this.isConnecting = false;
                return true;
            }
        }
        
        this.isConnecting = false;
        this._scheduleReconnect();
        return false;
    },
    
    _attemptConnection: function(server) {
        try {
            this.socket = new java.net.Socket();
            this.socket.setSoTimeout(BOT_CONFIG.TIMEOUT_RECEIVE);
            this.socket.connect(new java.net.InetSocketAddress(server.host, server.port), 5000);
            
            this.reader = new java.io.BufferedReader(new java.io.InputStreamReader(this.socket.getInputStream()));
            this.writer = new java.io.PrintWriter(new java.io.OutputStreamWriter(this.socket.getOutputStream()), true);
            
            DeviceInfo.socket = this.socket;
            Auth.socket = this.socket;
            
            if (!this._performHandshake()) {
                this.disconnect();
                return false;
            }
            
            BridgeLogger.info("NETWORK", "서버 연결 성공: " + server.name);
            Bridge.handleConnectionSuccess();
            this._startReceiveThread();
            
            return true;
        } catch (e) {
            BridgeLogger.error("NETWORK", "연결 실패: " + e);
            return false;
        }
    },
    
    _performHandshake: function() {
        try {
            var authPayload = Auth.createAuthPayload();
            var handshakeInfo = DeviceInfo.getHandshakeInfo();
            
            var handshakeData = {
                type: "handshake",
                auth: authPayload,
                info: handshakeInfo
            };
            
            this.sendMessage(handshakeData);
            
            var response = this.reader.readLine();
            if (!response) throw new Error("핸드셰이크 응답 없음");
            
            var result = JSON.parse(response);
            if (result.status === "accepted") {
                BridgeLogger.info("NETWORK", "핸드셰이크 성공");
                return true;
            } else {
                throw new Error("핸드셰이크 거부: " + (result.reason || "Unknown"));
            }
        } catch (e) {
            ErrorHandler.handle(e, "HANDSHAKE");
            return false;
        }
    },
    
    sendMessage: function(data) {
        if (!this.writer || !this.socket || !this.socket.isConnected()) {
            BridgeLogger.error("NETWORK", "서버에 연결되어 있지 않습니다.");
            return false;
        }
        
        try {
            var jsonStr = JSON.stringify(data);
            this.writer.println(jsonStr);
            return true;
        } catch (e) {
            ErrorHandler.handle(e, "SEND_MESSAGE");
            return false;
        }
    },
    
    _startReceiveThread: function() {
        var self = this;
        this.receiveThread = new java.lang.Thread(new java.lang.Runnable({
            run: function() {
                while (self.isConnected && self.socket && !self.socket.isClosed()) {
                    try {
                        var line = self.reader.readLine();
                        if (!line) {
                            throw new Error("서버 연결 종료");
                        }
                        
                        var message = JSON.parse(line);
                        Bridge.handleServerMessage(message);
                    } catch (e) {
                        var handled = ErrorHandler.handle(e, "RECEIVE_THREAD");
                        
                        if (handled.strategy === ErrorHandler.RECOVERY_STRATEGIES.RECONNECT) {
                            BridgeLogger.warn("NETWORK", "연결 끊김 감지, 재연결 시도");
                            self.handleDisconnection();
                            break;
                        }
                    }
                }
            }
        }));
        
        this.receiveThread.start();
    },
    
    handleDisconnection: function() {
        this.isConnected = false;
        this.disconnect();
        this._scheduleReconnect();
    },
    
    _scheduleReconnect: function() {
        if (BOT_CONFIG.MAX_RECONNECT_ATTEMPTS !== -1 && 
            this.reconnectAttempts >= BOT_CONFIG.MAX_RECONNECT_ATTEMPTS) {
            BridgeLogger.error("NETWORK", "최대 재연결 시도 횟수 초과");
            return;
        }
        
        var delay = this._getReconnectDelay();
        BridgeLogger.info("NETWORK", delay / 1000 + "초 후 재연결 시도 (시도 #" + (this.reconnectAttempts + 1) + ")");
        
        var self = this;
        setTimeout(function() {
            self.reconnectAttempts++;
            self.connectToServer();
        }, delay);
    },
    
    _getReconnectDelay: function() {
        var intervals = BOT_CONFIG.RECONNECT_INTERVALS;
        if (this.reconnectAttempts < intervals.length) {
            return intervals[this.reconnectAttempts];
        }
        return intervals[intervals.length - 1];
    },
    
    disconnect: function() {
        this.isConnected = false;
        
        try {
            if (this.receiveThread) {
                this.receiveThread.interrupt();
                this.receiveThread = null;
            }
        } catch (e) {}
        
        try {
            if (this.writer) {
                this.writer.close();
                this.writer = null;
            }
        } catch (e) {}
        
        try {
            if (this.reader) {
                this.reader.close();
                this.reader = null;
            }
        } catch (e) {}
        
        try {
            if (this.socket) {
                this.socket.close();
                this.socket = null;
            }
        } catch (e) {}
        
        BridgeLogger.info("NETWORK", "연결 종료됨");
    }
};

// =============================================================================
// 메시지 큐
// =============================================================================
var MessageQueue = {
    queue: [],
    maxSize: BOT_CONFIG.QUEUE_SIZE,
    
    add: function(message) {
        synchronized('messageQueue', function() {
            if (this.queue.length >= this.maxSize) {
                var removed = this.queue.shift();
                BridgeLogger.warn("QUEUE", "큐 크기 초과, 오래된 메시지 제거");
            }
            
            message.timestamp = Date.now();
            message.id = Utils.generateUUID();
            this.queue.push(message);
        }.bind(this));
    },
    
    process: function() {
        if (!Network.isConnected || this.queue.length === 0) return;
        
        var self = this;
        synchronized('messageQueue', function() {
            var now = Date.now();
            var toSend = [];
            
            self.queue = self.queue.filter(function(msg) {
                if (now - msg.timestamp > BOT_CONFIG.MESSAGE_TTL) {
                    BridgeLogger.warn("QUEUE", "TTL 초과 메시지 제거");
                    return false;
                }
                
                if (toSend.length < 10) {
                    toSend.push(msg);
                    return false;
                }
                return true;
            });
            
            toSend.forEach(function(msg) {
                if (!Network.sendMessage(msg)) {
                    self.queue.unshift(msg);
                }
            });
        });
    },
    
    clear: function() {
        synchronized('messageQueue', function() {
            this.queue = [];
            BridgeLogger.info("QUEUE", "메시지 큐 초기화");
        }.bind(this));
    }
};

// =============================================================================
// 방 관리
// =============================================================================
var RoomManager = {
    rooms: {},
    roomActivity: {},
    
    updateActivity: function(room) {
        this.roomActivity[room] = Date.now();
        if (!this.rooms[room]) {
            this.rooms[room] = {
                created: Date.now(),
                messageCount: 0
            };
        }
        this.rooms[room].messageCount++;
    },
    
    cleanupInactiveRooms: function() {
        var now = Date.now();
        var inactiveDays = BOT_CONFIG.ROOM_INACTIVE_DAYS * 24 * 60 * 60 * 1000;
        
        for (var room in this.roomActivity) {
            if (now - this.roomActivity[room] > inactiveDays) {
                delete this.rooms[room];
                delete this.roomActivity[room];
                BridgeLogger.info("ROOM", "비활성 방 정리: " + room);
            }
        }
    }
};

// =============================================================================
// 브릿지 코어
// =============================================================================
var Bridge = {
    startTime: Date.now(),
    messageProcessed: 0,
    isRunning: false,
    heartbeatTimer: null,
    queueTimer: null,
    cleanupTimer: null,
    
    initialize: function() {
        BridgeLogger.info("BRIDGE", "Bridge v" + BOT_CONFIG.VERSION + " 초기화 시작");
        
        this.isRunning = true;
        BridgeLogger.setLevel(BOT_CONFIG.LOG_LEVEL);
        
        // 네트워크 연결
        Network.connectToServer();
        
        // 주기적 작업 시작
        this.startPeriodicTasks();
        
        // 이벤트 리스너 등록 지연
        var self = this;
        setTimeout(function() {
            self.initializeEventListeners();
        }, BOT_CONFIG.INITIALIZATION_DELAY);
        
        BridgeLogger.info("BRIDGE", "Bridge 초기화 완료");
    },
    
    initializeEventListeners: function() {
        if (typeof Event !== 'undefined' && Event.NOTIFICATION_POSTED) {
            BridgeLogger.info("BRIDGE", "이벤트 리스너 등록");
        } else {
            BridgeLogger.warn("BRIDGE", "Event 상수를 찾을 수 없습니다. 기본 response 함수만 사용합니다.");
        }
    },
    
    startPeriodicTasks: function() {
        var self = this;
        
        // 하트비트
        this.heartbeatTimer = setInterval(function() {
            if (Network.isConnected) {
                Network.sendMessage({ type: "heartbeat", timestamp: Date.now() });
            }
        }, BOT_CONFIG.HEARTBEAT_INTERVAL);
        
        // 메시지 큐 처리
        this.queueTimer = setInterval(function() {
            MessageQueue.process();
        }, 1000);
        
        // 정리 작업
        this.cleanupTimer = setInterval(function() {
            self.performCleanup();
        }, BOT_CONFIG.CLEANUP_INTERVAL);
    },
    
    performCleanup: function() {
        BridgeLogger.info("BRIDGE", "정리 작업 시작");
        
        // 비활성 방 정리
        RoomManager.cleanupInactiveRooms();
        
        // 오래된 임시 파일 정리
        this.cleanupTempFiles();
        
        BridgeLogger.info("BRIDGE", "정리 작업 완료");
    },
    
    cleanupTempFiles: function() {
        try {
            var tempDir = new Packages.java.io.File(BOT_CONFIG.MEDIA_TEMP_DIR);
            if (tempDir.exists() && tempDir.isDirectory()) {
                var now = Date.now();
                var maxAge = BOT_CONFIG.TEMP_FILE_DAYS * 24 * 60 * 60 * 1000;
                
                var files = tempDir.listFiles();
                for (var i = 0; i < files.length; i++) {
                    if (now - files[i].lastModified() > maxAge) {
                        if (files[i].delete()) {
                            BridgeLogger.debug("CLEANUP", "임시 파일 삭제: " + files[i].getName());
                        }
                    }
                }
            }
        } catch (e) {
            BridgeLogger.error("CLEANUP", "임시 파일 정리 실패: " + e);
        }
    },
    
    processMessage: function(room, msg, sender, isGroupChat, replier, imageDB, packageName) {
        if (!this.isRunning) return;
        
        // 방 활동 업데이트
        RoomManager.updateActivity(room);
        
        // 메시지 준비
        var message = {
            type: "message",
            room: room,
            content: Utils.sanitizeMessage(msg),
            sender: sender,
            isGroupChat: isGroupChat,
            packageName: packageName,
            timestamp: Date.now()
        };
        
        // 큐에 추가
        MessageQueue.add(message);
        
        // 통계 업데이트
        this.messageProcessed++;
        
        BridgeLogger.debug("MESSAGE", "메시지 처리: " + room + " - " + sender);
    },
    
    handleServerMessage: function(message) {
        try {
            var type = message.type;
            
            switch (type) {
                case 'response':
                    this.handleResponse(message);
                    break;
                    
                case 'command':
                    this.handleCommand(message);
                    break;
                    
                case 'heartbeat':
                    BridgeLogger.debug("SERVER", "하트비트 응답");
                    break;
                    
                default:
                    BridgeLogger.warn("SERVER", "알 수 없는 메시지 타입: " + type);
            }
        } catch (e) {
            ErrorHandler.handle(e, "HANDLE_SERVER_MESSAGE");
        }
    },
    
    handleResponse: function(message) {
        try {
            var room = message.room;
            var content = message.content;
            var options = message.options || {};
            
            if (options.media && BOT_CONFIG.MEDIA_ENABLED) {
                this.sendMediaMessage(room, options.media, options.media_wait_time);
            } else if (content) {
                bot.send(room, content);
            }
        } catch (e) {
            ErrorHandler.handle(e, "HANDLE_RESPONSE");
        }
    },
    
    handleCommand: function(message) {
        var command = message.command;
        var params = message.params || {};
        
        switch (command) {
            case 'reload':
                BridgeLogger.info("COMMAND", "리로드 명령 수신");
                this.reload();
                break;
                
            case 'status':
                this.sendStatus();
                break;
                
            default:
                BridgeLogger.warn("COMMAND", "알 수 없는 명령: " + command);
        }
    },
    
    sendMediaMessage: function(room, mediaData, waitTime) {
        try {
            waitTime = waitTime || 0;
            
            var fileName = "media_" + Date.now() + "_" + Utils.generateUUID() + ".png";
            var filePath = MediaHandler.saveBase64Image(mediaData, fileName);
            
            if (!filePath) {
                BridgeLogger.error("MEDIA", "미디어 저장 실패");
                return;
            }
            
            if (waitTime > 0) {
                BridgeLogger.debug("MEDIA", "미디어 전송 대기: " + waitTime + "ms");
                setTimeout(function() {
                    Bridge._sendMediaFile(room, filePath);
                }, waitTime);
            } else {
                this._sendMediaFile(room, filePath);
            }
        } catch (e) {
            ErrorHandler.handle(e, "SEND_MEDIA");
        }
    },
    
    _sendMediaFile: function(room, filePath) {
        try {
            var uri = MediaHandler.getFileUri(filePath);
            if (!uri) {
                BridgeLogger.error("MEDIA", "URI 생성 실패");
                return;
            }
            
            var intent = new android.content.Intent(android.content.Intent.ACTION_SEND);
            intent.setType("image/*");
            intent.putExtra(android.content.Intent.EXTRA_STREAM, uri);
            intent.setPackage(BOT_CONFIG.KAKAOTALK_PACKAGE_NAME);
            intent.addFlags(android.content.Intent.FLAG_GRANT_READ_URI_PERMISSION);
            intent.addFlags(android.content.Intent.FLAG_ACTIVITY_NEW_TASK);
            
            App.getContext().startActivity(intent);
            
            BridgeLogger.info("MEDIA", "미디어 전송 완료: " + room);
        } catch (e) {
            ErrorHandler.handle(e, "SEND_MEDIA_FILE");
        }
    },
    
    sendStatus: function() {
        var uptime = Date.now() - this.startTime;
        var status = {
            type: "status",
            version: BOT_CONFIG.VERSION,
            uptime: uptime,
            messagesProcessed: this.messageProcessed,
            queueSize: MessageQueue.queue.length,
            activeRooms: Object.keys(RoomManager.rooms).length,
            isConnected: Network.isConnected,
            currentServer: Network.currentServer ? Network.currentServer.name : null
        };
        
        Network.sendMessage(status);
    },
    
    handleConnectionSuccess: function() {
        // 큐 처리 시작
        MessageQueue.process();
        
        // 상태 전송
        this.sendStatus();
    },
    
    reload: function() {
        BridgeLogger.info("BRIDGE", "리로드 시작");
        this.shutdown();
        this.initialize();
    },
    
    shutdown: function() {
        BridgeLogger.info("BRIDGE", "종료 시작");
        
        this.isRunning = false;
        
        // 타이머 정리
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = null;
        }
        
        if (this.queueTimer) {
            clearInterval(this.queueTimer);
            this.queueTimer = null;
        }
        
        if (this.cleanupTimer) {
            clearInterval(this.cleanupTimer);
            this.cleanupTimer = null;
        }
        
        // 네트워크 종료
        Network.disconnect();
        
        // 큐 정리
        MessageQueue.clear();
        
        BridgeLogger.info("BRIDGE", "종료 완료");
    }
};

// =============================================================================
// MessengerBotR API 구현
// =============================================================================
function response(room, msg, sender, isGroupChat, replier, imageDB, packageName) {
    try {
        Bridge.processMessage(room, msg, sender, isGroupChat, replier, imageDB, packageName);
    } catch (e) {
        ErrorHandler.handle(e, "RESPONSE");
    }
}

function onCreate(savedInstanceState, activity) {
    Bridge.initialize();
}

function onStart(activity) {
    BridgeLogger.info("LIFECYCLE", "onStart");
}

function onResume(activity) {
    BridgeLogger.info("LIFECYCLE", "onResume");
}

function onPause(activity) {
    BridgeLogger.info("LIFECYCLE", "onPause");
}

function onStop(activity) {
    BridgeLogger.info("LIFECYCLE", "onStop");
}

// 이벤트 리스너 (Event가 정의되어 있는 경우에만 작동)
function onNotificationPosted(sbn, sm) {
    if (!Bridge.isRunning) return;
    BridgeLogger.debug("EVENT", "알림 수신");
}

// Bot 객체 (존재하지 않을 경우를 대비)
if (typeof bot === 'undefined') {
    var bot = {
        send: function(room, message) {
            Api.replyRoom(room, message);
        }
    };
}

// 초기화
Bridge.initialize();