-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: db2.j-touch.com
-- 생성 시간: 25-06-06 00:00
-- 서버 버전: 10.3.7-MariaDB-log
-- PHP 버전: 8.2.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- 데이터베이스: `kakao_bot`
--

-- --------------------------------------------------------

--
-- 테이블 구조 `kb_room_global_config`
--

CREATE TABLE `kb_room_global_config` (
  `config_key` varchar(50) NOT NULL COMMENT '설정 키',
  `config_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '글로벌(기본) 설정값',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 테이블의 덤프 데이터 `kb_room_global_config`
--

INSERT INTO `kb_room_global_config` (`config_key`, `config_json`, `updated_at`) VALUES
('allowed_categories', '[\"관리\", \"일반\", \"성경\", \"주식\", \"AI(일반)\", \"AI(프로필분석)\", \"뉴스\", \"유틸리티\", \"채팅\", \"날씨\", \"봇 자동응답\", \"게임(오목)\", \"법률\"]', '2025-05-24 22:57:44'),
('chat_sessions', '{\r\n    \"private_chat\": {\r\n      \"enabled\": true,\r\n      \"session_timeout_minutes\": 10,\r\n      \"daily_limit_per_user\": 1,\r\n      \"offer_extension\": true\r\n    },\r\n    \"group_chat\": {\r\n      \"enabled\": true,\r\n      \"session_timeout_minutes\": 15,\r\n      \"daily_limit_per_room\": 1,\r\n      \"offer_extension\": true\r\n    }\r\n  }', '2025-05-24 22:57:51'),
('default_bibles', '111111111', '2025-05-24 23:06:10'),
('enable_investment_news', '{\r\n    \"TradingView\": [\r\n      \"주식\", \"ETFs\", \"크립토\", \"외환\", \"지수\", \"선물\", \"국채\", \"경제\"\r\n    ]\r\n  }', '2025-05-24 22:58:29'),
('ignored_users', '[{\"user_hash\": \"e1e3634ea711568c5b2721dbd936046368cb488a4f6bb9f82e5c5558eaa03bd7\", \"nickname\": \"드리고-O\", \"is_bot\": true, \"no_response\": true, \"no_logging\": true}]', '2025-05-24 23:05:14'),
('llm_settings', '{\r\n    \"show_usage\": true,\r\n    \"show_cost\": true,\r\n    \"show_model\": true,\r\n    \"show_monthly_usage\": true,\r\n    \"show_lifetime_usage\": false,\r\n    \"usage_display_format\": \"[모델: {model}] 토큰: {total_tokens}개 ({cost:.2f}원) | 이번달: {monthly_cost:.2f}원\",\r\n    \"custom_message\": \"후원 계좌: 123-456-789 (홍길동)\",\r\n    \"operation_mode\": \"mixed\",\r\n    \"system_calls\": {\r\n      \"usage_tracking\": true,\r\n      \"default_model\": \"deepseek-chat\",\r\n      \"cost_tracking\": true,\r\n      \"deduct_from_channel\": false\r\n    },\r\n    \"user_calls\": {\r\n      \"daily_limit\": 0,\r\n      \"monthly_limit\": 0,\r\n      \"require_credits\": false,\r\n      \"free_credits_monthly\": 500,\r\n      \"allowed_prefixes\": [\">\", \">>\", \">>>\"],\r\n      \"deduct_from_channel\": true,\r\n      \"deduct_from_user\": false,\r\n      \"fallback_to_user\": true,\r\n      \"admin_free_usage\": true\r\n    },\r\n    \"free_usage_rules\": {\r\n      \"free_for_users\": [\"유저해시1\", \"유저해시2\"],\r\n      \"free_prefixes\": [\">\"],\r\n      \"daily_free_quota\": 5,\r\n      \"monthly_free_quota\": 100\r\n    }\r\n  }', '2025-05-24 22:56:51'),
('log_settings', '{\r\n    \"disable_chat_logs\": false,\r\n    \"disable_command_logs\": false,\r\n    \"log_level\": \"DEBUG\"\r\n  }', '2025-05-24 22:57:04'),
('omok_settings', '{\r\n    \"enabled\": true,\r\n    \"ai_mode\": [\"기본\", \"고급\"],\r\n    \"default_ai_mode\": \"기본\",\r\n    \"default_ai_level\": 5,\r\n    \"ban_spot\": true,\r\n    \"move_timeout_seconds\": 60,\r\n    \"start_timeout_seconds\": 300,\r\n    \"debug_allowed\": false,\r\n    \"board_style\": \"wood\",\r\n    \"board_style_description\": \"기본 나무 색상의 심플한 디자인\",\r\n    \"available_rule_sets\": [\"freestyle\", \"standard\", \"pro\", \"longpro\", \"renju\"],\r\n    \"default_rule_set\": \"freestyle\"\r\n  }', '2025-05-24 22:57:22'),
('room_concurrency', '2', '2025-05-24 23:02:44'),
('webpage_summary', '{\r\n    \"enabled\": true,\r\n    \"auto_detection\": {\r\n      \"enabled\": true,\r\n      \"daily_limit\": 100,\r\n      \"cooldown_seconds\": 5,\r\n      \"show_waiting_message\": false\r\n    },\r\n    \"command\": {\r\n      \"enabled\": true,\r\n      \"room_daily_limit\": 100,\r\n      \"user_daily_limit\": 10\r\n    },\r\n    \"kakao_readmore\": { \"type\": \"chars\", \"value\": 17 }\r\n  }', '2025-05-24 22:58:19'),
('youtube_summary', '{\r\n    \"enabled\": true,\r\n    \"auto_detection\": {\r\n      \"enabled\": true,\r\n      \"daily_limit\": 100,\r\n      \"cooldown_seconds\": 5,\r\n      \"show_waiting_message\": false\r\n    },\r\n    \"command\": {\r\n      \"enabled\": true,\r\n      \"room_daily_limit\": 100,\r\n      \"user_daily_limit\": 10\r\n    },\r\n    \"transcription\": {\r\n      \"enabled\": true,\r\n      \"max_duration_minutes\": 10,\r\n      \"provider\": \"whisper\",\r\n      \"model\": \"whisper-1\",\r\n      \"daily_limit\": 50\r\n    },\r\n    \"kakao_readmore\": { \"type\": \"lines\", \"value\": 1 }\r\n  }', '2025-05-24 22:58:08');

--
-- 덤프된 테이블의 인덱스
--

--
-- 테이블의 인덱스 `kb_room_global_config`
--
ALTER TABLE `kb_room_global_config`
  ADD PRIMARY KEY (`config_key`);
COMMIT;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: db2.j-touch.com
-- 생성 시간: 25-06-06 00:00
-- 서버 버전: 10.3.7-MariaDB-log
-- PHP 버전: 8.2.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- 데이터베이스: `kakao_bot`
--

-- --------------------------------------------------------

--
-- 테이블 구조 `kb_rooms`
--

CREATE TABLE `kb_rooms` (
  `room_id` varchar(50) NOT NULL COMMENT '카카오톡 방/channelId',
  `bot_name` varchar(30) NOT NULL COMMENT '봇 그룹/구분용(최상위 키, 필수)',
  `room_name` varchar(255) NOT NULL COMMENT '방 이름',
  `bot_nickname` varchar(30) DEFAULT NULL COMMENT '(옵션) 봇이 1개만 있는 경우에만 LLM 시스템 프롬프트에서 사용하는 닉네임(선택적)',
  `room_concurrency` int(11) DEFAULT 2 COMMENT '동시 작업 허용 수',
  `room_owners` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '방 소유자 목록',
  `allowed_categories` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '허용 카테고리',
  `log_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '로그 설정',
  `ignored_users` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '무시 유저 목록',
  `nickname_alert` tinyint(1) DEFAULT 0 COMMENT '닉네임 알림 여부',
  `llm_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'LLM 사용량/권한/비용 등 방 전체 LLM 정책 통합 관리(개별 기능 아님)',
  `chat_sessions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '채팅 세션(1:1, 그룹 등) 관련 설정',
  `conversation_summary` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '대화 요약 기능 설정',
  `youtube_summary` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '유튜브 요약 기능 설정',
  `webpage_summary` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '웹페이지 요약 기능 설정',
  `enable_investment_news` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '투자 뉴스 기능 설정',
  `default_bibles` varchar(32) DEFAULT NULL COMMENT '기본 성경 설정',
  `omok_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '오목 게임 기능 설정',
  `conversation_join` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '대화 합류(자동참여) 기능 설정',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 테이블의 덤프 데이터 `kb_rooms`
--

INSERT INTO `kb_rooms` (`room_id`, `bot_name`, `room_name`, `bot_nickname`, `room_concurrency`, `room_owners`, `allowed_categories`, `log_settings`, `ignored_users`, `nickname_alert`, `llm_settings`, `chat_sessions`, `conversation_summary`, `youtube_summary`, `webpage_summary`, `enable_investment_news`, `default_bibles`, `omok_settings`, `conversation_join`, `created_at`, `updated_at`) VALUES
('18263272052425167', 'LOA.i', '성경연구방', '로아', NULL, '[\"user_hash_1\", \"user_hash_2\"]', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, '111111111', NULL, NULL, '2025-05-24 23:01:00', '2025-06-05 23:34:52'),
('18275476525650435', 'LOA.i', '성경회개', '로아', NULL, '[\"user_hash_1\", \"user_hash_2\"]', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, '111111111', NULL, NULL, '2025-05-24 23:01:02', '2025-06-05 23:35:15'),
('18317005864945719', 'LOA.i', '주식 스윙/중기 정예공부방', '로아', NULL, '[\"user_hash_1\", \"user_hash_2\"]', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, '111111111', NULL, NULL, '2025-05-24 23:01:08', '2025-06-05 23:36:30'),
('18358994267380807', 'LOA.i', '금융공학 퀀트 트레이딩 (자동매매, 코딩, API)', '로아', NULL, '[\"user_hash_1\", \"user_hash_2\"]', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, '111111111', NULL, NULL, '2025-05-24 23:01:06', '2025-06-05 23:36:08'),
('18389053489704706', 'LOA.i', '매매동향과 전체평단으로 보는 주식 종목 분석!', '로아', NULL, '[\"user_hash_1\", \"user_hash_2\"]', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, '111111111', NULL, NULL, '2025-05-24 23:01:05', '2025-06-05 23:35:49'),
('18396628723103362', 'LOA.i', '예수 성경 말씀 이야기(성경토론, 성경연구)', '로아', NULL, '[\"user_hash_1\", \"user_hash_2\"]', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, '111111111', NULL, NULL, '2025-05-24 23:00:05', '2025-06-05 23:31:00'),
('18398338829933617', 'LOA.i', '카카오톡 봇 커뮤니티 | 카봇커', '로아', NULL, '[\"user_hash_1\", \"user_hash_2\"]', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, '111111111', NULL, NULL, '2025-05-24 23:01:09', '2025-05-24 23:05:24'),
('18402322420232267', 'LOA.i', '[성경공부] 은혜+진리=사랑 (매일성경, 성경연구)', '로아', NULL, '[\"user_hash_1\", \"user_hash_2\"]', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, '111111111', NULL, NULL, '2025-05-24 23:00:03', '2025-06-05 23:30:43'),
('18435853038217912', 'LOA.i', '진흙 구덩이 자유 토론방', '로아', NULL, '[\"user_hash_1\", \"user_hash_2\"]', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, '111111111', NULL, NULL, '2025-05-24 23:01:01', '2025-06-05 23:35:05'),
('18436882630596864', 'LOA.i', 'IONQ 주주 모여라!', '로아', NULL, '[\"user_hash_1\", \"user_hash_2\"]', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, '111111111', NULL, NULL, '2025-05-24 23:01:07', '2025-06-05 23:36:20'),
('18441338362760746', 'LOA.i', '주식, 해외선물 자동매매 소수정예 정보공유', '로아', NULL, '[\"user_hash_1\", \"user_hash_2\"]', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, '111111111', NULL, NULL, '2025-05-24 23:01:09', '2025-05-24 23:05:24'),
('18445682959392711', 'LOA.i', 'mybot', '로아', NULL, '[\"user_hash_1\", \"user_hash_2\"]', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, '111111111', NULL, NULL, '2025-05-24 23:00:02', '2025-06-05 23:30:12'),
('18446369739418674', 'LOA.i', 'LOA.i', '로아', NULL, '[\"user_hash_1\", \"user_hash_2\"]', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, '111111111', NULL, NULL, '2025-05-24 22:01:09', '2025-06-05 23:26:49'),
('401611339715053', 'LOA.i', '2024셀모임', '로아', NULL, '[\"user_hash_1\", \"user_hash_2\"]', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, '111111111', NULL, NULL, '2025-05-24 23:01:03', '2025-06-05 23:35:27'),
('425089245740374', 'LOA.i', '화도교회 고3 1반', '로아', NULL, '[\"user_hash_1\", \"user_hash_2\"]', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, '111111111', NULL, NULL, '2025-05-24 23:01:04', '2025-06-05 23:35:38');

--
-- 덤프된 테이블의 인덱스
--

--
-- 테이블의 인덱스 `kb_rooms`
--
ALTER TABLE `kb_rooms`
  ADD PRIMARY KEY (`room_id`);
COMMIT;
-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: db2.j-touch.com
-- 생성 시간: 25-06-06 00:00
-- 서버 버전: 10.3.7-MariaDB-log
-- PHP 버전: 8.2.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- 데이터베이스: `kakao_bot`
--

-- --------------------------------------------------------

--
-- 테이블 구조 `kb_room_schedules`
--

CREATE TABLE `kb_room_schedules` (
  `schedule_id` int(11) NOT NULL COMMENT '스케줄 고유키',
  `room_id` varchar(50) NOT NULL COMMENT '방/channelId',
  `bot_name` varchar(30) NOT NULL COMMENT '봇 그룹/구분용(최상위 키, 필수)',
  `days` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '요일 배열',
  `times` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '시간 배열',
  `messages` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '메시지 배열',
  `tts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'TTS 설정',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 테이블의 덤프 데이터 `kb_room_schedules`
--

INSERT INTO `kb_room_schedules` (`schedule_id`, `room_id`, `bot_name`, `days`, `times`, `messages`, `tts`, `created_at`, `updated_at`) VALUES
(1, '18446369739418674', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\"]', '[\"18:00\"]', '[\"# 오늘의 대화 요약!\"]', '{\r\n    \"enabled\": true,\r\n    \"config\": {\r\n      \"language\": \"auto\",\r\n      \"gender\": \"F\",\r\n      \"voice\": \"auto\",\r\n      \"intro\": [\r\n        \"음성으로 녹음해 보았어요! 🧏\",\r\n        \"내용을 녹음해 보았어요. 🔔\",\r\n        \"직접 읽어봤어요! 🎙️\",\r\n        \"이 내용을 들려드릴게요. 🎧\",\r\n        \"음성으로 정리해 드릴게요! 📋\",\r\n        \"제가 읽어볼게요~ 🎤\",\r\n        \"짧게 녹음해봤어요! 🎙️\",\r\n        \"핵심만 콕! 읽어드릴게요. 🧠\",\r\n        \"요약된 내용을 들어보세요. 📝\",\r\n        \"들어보는 게 더 편하죠? 👂\",\r\n        \"말로 전해드릴게요! 🗣️\"\r\n      ]\r\n    }\r\n  }', '2025-05-24 23:36:06', '2025-05-24 23:36:06'),
(2, '18446369739418674', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\"]', '[\"17:30\"]', '[\"# 환율\"]', NULL, '2025-05-24 23:36:06', '2025-05-24 23:36:06'),
(3, '18446369739418674', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\"]', '[\"10:30\"]', '[\"# [블룸버그 오늘의 5가지 이슈]\"]', NULL, '2025-05-24 23:36:06', '2025-05-24 23:36:06'),
(4, '18446369739418674', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"01:10\"]', '[\"# [오늘의 성경묵상]\"]', '{\"enabled\": true}', '2025-05-24 23:36:06', '2025-05-24 23:36:06'),
(5, '18446369739418674', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"01:21\"]', '[\"# [매일성경]\"]', '{\"enabled\": true}', '2025-05-24 23:36:06', '2025-05-24 23:36:06'),
(6, '18446369739418674', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"13:00\",\"23:38\",\"23:39\",\"23:51\",\"14:52\",\"14:16\",\"14:17\",\"14:18\",\"14:19\",\"14:20\"]', '[\r\n    \"# echo (꺄아) 개역한글\\n\\n새 계명을 너희에게 주노니 서로 사랑하라 내가 너희를 사랑한 것 같이 너희도 서로 사랑하라 - 요 13장 34절\",\r\n    \"# echo (꺄아) 개역한글\\n\\n우리가 형제를 사랑함으로 사망에서 옮겨 생명으로 들어간 줄을 알거니와 사랑치 아니하는 자는 사망에 거하느니라 - 요일 3.14\",\r\n    \"# echo (꺄아) 개역한글\\n\\n영생은 곧 유일하신 참 하나님과 그의 보내신 자 예수 그리스도를 아는 것이니이다 - 요 17:3\",\r\n    \"# echo (꺄아) 개역한글\\n\\n사랑하는 자들아 우리가 서로 사랑하자 사랑은 하나님께 속한 것이니 - 요일 4:7~8\",\r\n    \"# echo (꺄아) 개역한글\\n\\n피차 사랑의 빚 외에는 아무에게든지 아무 빚도 지지 말라 남을 사랑하는 자는 율법을 다 이루었느니라 - 롬 13:8\",\r\n    \"# echo (꺄아) 개역한글\\n\\n사랑은 이웃에게 악을 행하지 아니하나니 그러므로 사랑은 율법의 완성이니라 - 롬 13:10\",\r\n    \"# echo (꺄아) 개역한글\\n\\n온 율법은 네 이웃 사랑하기를 네 몸 같이 하라 하신 한 말씀에 이루었나니 - 갈 5:14\",\r\n    \"# echo (꺄아) 개역한글\\n\\n너희가 진리를 순종함으로 너희 영혼을 깨끗하게 하여 거짓이 없이 형제를 사랑하기에 이르렀으니 마음으로 뜨겁게 피차 사랑하라 - 벧전 1:22\",\r\n    \"# echo (꺄아) 개역한글\\n\\n저가 빛 가운데 계신 것 같이 우리도 빛 가운데 행하면 우리가 서로 사귐이 있고 그 아들 예수의 피가 우리를 모든 죄에서 깨끗하게 하실 것이요 - 요일 1:7\",\r\n    \"# echo (꺄아) 개역한글\\n\\n미움은 다툼을 일으켜도 사랑은 모든 허물을 가리느니라 - 잠 10:12\",\r\n    \"# echo (꺄아) 개역한글\\n\\n내가 내게 있는 모든 것으로 구제하고 또 내 몸을 불사르게 내어 줄지라도 사랑이 없으면 내게 아무 유익이 없느니라 - 고전 13:3\",\r\n    \"# echo (꺄아) 개역한글\\n\\n이 모든 것 위에 사랑을 더하라 이는 온전하게 매는 띠니라 - 골 3:14\",\r\n    \"# echo (꺄아) 개역한글\\n\\n7 사랑하는 자들아 우리가 서로 사랑하자 사랑은 하나님께 속한 것이니 사랑하는 자마다 하나님께로 나서 하나님을 알고 8 사랑하지 아니하는 자는 하나님을 알지 못하나니 이는 하나님은 사랑이심이라 - 요일 4:7~8\",\r\n    \"# echo (꺄아) 개역한글\\n\\n10 내가 아버지의 계명을 지켜 그의 사랑 안에 거하는 것 같이 너희도 내 계명을 지키면 내 사랑 안에 거하리라 11 내가 이것을 너희에게 이름은 내 기쁨이 너희 안에 있어 너희 기쁨을 충만하게 하려 함이니라 12 내 계명은 곧 내가 너희를 사랑한 것 같이 너희도 서로 사랑하라 하는 이것이니라 - 요 15:10~12\",\r\n    \"# echo (꺄아) 개역한글\\n\\n또 사랑은 이것이니 우리가 그 계명을 좇아 행하는 것이요 계명은 이것이니 너희가 처음부터 들은 바와 같이 그 가운데서 행하라 하심이라 - 요이 1:6\",\r\n    \"# echo (꺄아) 개역한글\\n\\n무엇보다도 열심으로 서로 사랑할지니 사랑은 허다한 죄를 덮느니라 - 벧전 4:8\",\r\n    \"# echo (꺄아) 개역한글\\n\\n12 내 계명은 곧 내가 너희를 사랑한 것 같이 너희도 서로 사랑하라 하는 이것이니라 13 사람이 친구를 위하여 자기 목숨을 버리면 이에서 더 큰 사랑이 없나니 14 너희가 나의 명하는 대로 행하면 곧 나의 친구라 - 요 15:12~14\",\r\n    \"# echo (꺄아) 개역한글\\n\\n우리가 우리에게 죄 지은 자를 사하여 준 것 같이 우리 죄를 사하여 주옵시고 - 마 6:12\"\r\n  ]', NULL, '2025-05-24 23:36:06', '2025-05-24 23:36:06'),
(7, '18446369739418674', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\"]', '[\"18:15\",\"00:22\",\"23:20\",\"00:21\"]', '[\"# [오늘의 투자격언]\"]', NULL, '2025-05-24 23:36:06', '2025-05-24 23:36:06'),
(8, '18446369739418674', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"23:49\"]', '[\"# echo (꺄아) 개역한글: 요한복음 13장 34절\\n\\n새 계명을 너희에게 주노니 서로 사랑하라 내가 너희를 사랑한 것 같이 너희도 서로 사랑하라\"]', NULL, '2025-05-24 23:36:06', '2025-05-24 23:36:06'),
(9, '18446369739418674', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"07:00\",\"00:21\"]', '[\"# [자동응답] 오늘날씨\"]', NULL, '2025-05-24 23:36:06', '2025-05-24 23:36:06'),
(13, '18445682959392711', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"23:37\",\"23:38\",\"23:39\",\"14:27\",\"14:28\",\"13:46\",\"13:47\",\"13:48\",\"13:49\",\"13:50\"]', '[\"# echo (꺄아) 개역한글\\n\\n새 계명을 너희에게 주노니 서로 사랑하라 내가 너희를 사랑한 것 같이 너희도 서로 사랑하라 - 요 13.34\"]', NULL, '2025-05-24 23:59:00', '2025-05-24 23:59:00'),
(14, '18402322420232267', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"11:00\"]', '[\r\n\"# echo (꺄아) 개역한글: 요한복음 13장 34절\\n\\n새 계명을 너희에게 주노니 서로 사랑하라 내가 너희를 사랑한 것 같이 너희도 서로 사랑하라\",\r\n\"# echo (꺄아) 개역한글: 요한일서 3장 13절\\n\\n우리가 형제를 사랑함으로 사망에서 옮겨 생명으로 들어간 줄을 알거니와 사랑치 아니하는 자는 사망에 거하느니라\",\r\n\"# echo (꺄아) 개역한글: 요한복음 17장 3절\\n\\n영생은 곧 유일하신 참 하나님과 그의 보내신 자 예수 그리스도를 아는 것이니이다\",\r\n\"# echo (꺄아) 개역한글: 요한일서 4장 7~8절\\n\\n사랑하는 자들아 우리가 서로 사랑하자 사랑은 하나님께 속한 것이니\",\r\n\"# echo (꺄아) 개역한글: 로마서 13장 8절\\n\\n피차 사랑의 빚 외에는 아무에게든지 아무 빚도 지지 말라 남을 사랑하는 자는 율법을 다 이루었느니라\",\r\n\"# echo (꺄아) 개역한글: 로마서 13장 10절\\n\\n사랑은 이웃에게 악을 행하지 아니하나니 그러므로 사랑은 율법의 완성이니라\",\r\n\"# echo (꺄아) 개역한글: 갈라디아서 5장 14절\\n\\n온 율법은 네 이웃 사랑하기를 네 몸 같이 하라 하신 한 말씀에 이루었나니\",\r\n\"# echo (꺄아) 개역한글: 베드로전서 1장 22절\\n\\n너희가 진리를 순종함으로 너희 영혼을 깨끗하게 하여 거짓이 없이 형제를 사랑하기에 이르렀으니 마음으로 뜨겁게 피차 사랑하라\",\r\n\"# echo (꺄아) 개역한글: 요한일서 1장 7절\\n\\n저가 빛 가운데 계신 것 같이 우리도 빛 가운데 행하면 우리가 서로 사귐이 있고 그 아들 예수의 피가 우리를 모든 죄에서 깨끗하게 하실 것이요\",\r\n\"# echo (꺄아) 개역한글: 잠언 10장 12절\\n\\n미움은 다툼을 일으켜도 사랑은 모든 허물을 가리느니라\",\r\n\"# echo (꺄아) 개역한글: 고린도전서 13장 3절\\n\\n내가 내게 있는 모든 것으로 구제하고 또 내 몸을 불사르게 내어 줄지라도 사랑이 없으면 내게 아무 유익이 없느니라\",\r\n\"# echo (꺄아) 개역한글: 골로세서 3장 14절\\n\\n이 모든 것 위에 사랑을 더하라 이는 온전하게 매는 띠니라 \",\r\n\"# echo (꺄아) 개역한글: 요한일서 4장 7~8절\\n\\n7 사랑하는 자들아 우리가 서로 사랑하자 사랑은 하나님께 속한 것이니 사랑하는 자마다 하나님께로 나서 하나님을 알고 8 사랑하지 아니하는 자는 하나님을 알지 못하나니 이는 하나님은 사랑이심이라\",\r\n\"# echo (꺄아) 개역한글: 요한복음 15장 10~12절\\n\\n10 내가 아버지의 계명을 지켜 그의 사랑 안에 거하는 것 같이 너희도 내 계명을 지키면 내 사랑 안에 거하리라 11 내가 이것을 너희에게 이름은 내 기쁨이 너희 안에 있어 너희 기쁨을 충만하게 하려 함이니라 12 내 계명은 곧 내가 너희를 사랑한 것 같이 너희도 서로 사랑하라 하는 이것이니라\",\r\n\"# echo (꺄아) 개역한글: 요한이서 1장 6절\\n\\n또 사랑은 이것이니 우리가 그 계명을 좇아 행하는 것이요 계명은 이것이니 너희가 처음부터 들은 바와 같이 그 가운데서 행하라 하심이라\",\r\n\"# echo (꺄아) 개역한글: 베드로전서 4장 8절\\n\\n무엇보다도 열심으로 서로 사랑할지니 사랑은 허다한 죄를 덮느니라\",\r\n\"# echo (꺄아) 개역한글: 요한복음 15장 12~14절\\n\\n12 내 계명은 곧 내가 너희를 사랑한 것 같이 너희도 서로 사랑하라 하는 이것이니라 13 사람이 친구를 위하여 자기 목숨을 버리면 이에서 더 큰 사랑이 없나니 14 너희가 나의 명하는 대로 행하면 곧 나의 친구라\",\r\n\"# echo (꺄아) 개역한글: 마태복음 6장 12절\\n\\n우리가 우리에게 죄 지은 자를 사하여 준 것 같이 우리 죄를 사하여 주옵시고\"\r\n]', NULL, '2025-05-25 00:06:13', '2025-06-05 22:41:02'),
(15, '18402322420232267', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"09:00\"]', '[\"# [매일성경]\"]', '{\"enabled\": true}', '2025-05-25 00:06:13', '2025-06-05 22:41:02'),
(16, '18402322420232267', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"11:59\"]', '[\"# [오늘의 성경묵상]\"]', '{\"enabled\": true}', '2025-05-25 00:06:13', '2025-06-05 22:41:02'),
(17, '18402322420232267', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"07:30\"]', '[\"# [자동응답] 오늘날씨\"]', NULL, '2025-05-25 00:06:13', '2025-06-05 22:41:02'),
(18, '18402322420232267', 'LOA.i', '[\"금\"]', '[\"18:00\"]', '[\r\n\"# echo (굿) \\n\\n즐거운 주말 되세요.\",\r\n\"# echo (굿) \\n\\n한 주 동안 수고 많으셨습니다. 편안한 주말 보내세요.\",\r\n\"# echo (굿) \\n\\n충분히 쉬시고 행복한 주말 보내시길 바랍니다!\",\r\n\"# echo (굿) \\n\\n행복이 가득한 주말 되세요.\",\r\n\"# echo (굿) \\n\\n따뜻한 햇살처럼 편안하고 기분 좋은 주말 되세요 🌿\",\r\n\"# echo (굿) \\n\\n좋아하는 것들로 가득 채운 행복한 주말 되시길 바래요!\",\r\n\"# echo (굿) \\n\\n주말엔 힐링 가득! 충전 잘 하시고 멋진 한 주 준비하세요 🔋\",\r\n\"# echo (굿) \\n\\n이번 주말엔 웃음꽃이 가득 피어나길 바랍니다 😄\",\r\n\"# echo (굿) \\n\\n행복은 주말에 두 배! 즐겁고 신나는 시간 보내세요 ✨\",\r\n\"# echo (굿) \\n\\n좋은 사람들과 멋진 주말 만드시고 활력 충전하세요!\"\r\n]', '{\"enabled\": true}', '2025-05-25 00:06:13', '2025-06-05 22:41:02'),
(19, '18396628723103362', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"00:10\"]', '[\r\n\"# echo (꺄아) 개역한글: 요한복음 13장 34절\\n\\n새 계명을 너희에게 주노니 서로 사랑하라 내가 너희를 사랑한 것 같이 너희도 서로 사랑하라\",\r\n\"# echo (꺄아) 개역한글: 요한일서 3장 13절\\n\\n우리가 형제를 사랑함으로 사망에서 옮겨 생명으로 들어간 줄을 알거니와 사랑치 아니하는 자는 사망에 거하느니라\",\r\n\"# echo (꺄아) 개역한글: 요한복음 17장 3절\\n\\n영생은 곧 유일하신 참 하나님과 그의 보내신 자 예수 그리스도를 아는 것이니이다\",\r\n\"# echo (꺄아) 개역한글: 요한일서 4장 7~8절\\n\\n사랑하는 자들아 우리가 서로 사랑하자 사랑은 하나님께 속한 것이니\",\r\n\"# echo (꺄아) 개역한글: 로마서 13장 8절\\n\\n피차 사랑의 빚 외에는 아무에게든지 아무 빚도 지지 말라 남을 사랑하는 자는 율법을 다 이루었느니라\",\r\n\"# echo (꺄아) 개역한글: 로마서 13장 10절\\n\\n사랑은 이웃에게 악을 행하지 아니하나니 그러므로 사랑은 율법의 완성이니라\",\r\n\"# echo (꺄아) 개역한글: 갈라디아서 5장 14절\\n\\n온 율법은 네 이웃 사랑하기를 네 몸 같이 하라 하신 한 말씀에 이루었나니\",\r\n\"# echo (꺄아) 개역한글: 베드로전서 1장 22절\\n\\n너희가 진리를 순종함으로 너희 영혼을 깨끗하게 하여 거짓이 없이 형제를 사랑하기에 이르렀으니 마음으로 뜨겁게 피차 사랑하라\",\r\n\"# echo (꺄아) 개역한글: 요한일서 1장 7절\\n\\n저가 빛 가운데 계신 것 같이 우리도 빛 가운데 행하면 우리가 서로 사귐이 있고 그 아들 예수의 피가 우리를 모든 죄에서 깨끗하게 하실 것이요\",\r\n\"# echo (꺄아) 개역한글: 잠언 10장 12절\\n\\n미움은 다툼을 일으켜도 사랑은 모든 허물을 가리느니라\",\r\n\"# echo (꺄아) 개역한글: 고린도전서 13장 3절\\n\\n내가 내게 있는 모든 것으로 구제하고 또 내 몸을 불사르게 내어 줄지라도 사랑이 없으면 내게 아무 유익이 없느니라\",\r\n\"# echo (꺄아) 개역한글: 골로세서 3장 14절\\n\\n이 모든 것 위에 사랑을 더하라 이는 온전하게 매는 띠니라 \",\r\n\"# echo (꺄아) 개역한글: 요한일서 4장 7~8절\\n\\n7 사랑하는 자들아 우리가 서로 사랑하자 사랑은 하나님께 속한 것이니 사랑하는 자마다 하나님께로 나서 하나님을 알고 8 사랑하지 아니하는 자는 하나님을 알지 못하나니 이는 하나님은 사랑이심이라\",\r\n\"# echo (꺄아) 개역한글: 요한복음 15장 10~12절\\n\\n10 내가 아버지의 계명을 지켜 그의 사랑 안에 거하는 것 같이 너희도 내 계명을 지키면 내 사랑 안에 거하리라 11 내가 이것을 너희에게 이름은 내 기쁨이 너희 안에 있어 너희 기쁨을 충만하게 하려 함이니라 12 내 계명은 곧 내가 너희를 사랑한 것 같이 너희도 서로 사랑하라 하는 이것이니라\",\r\n\"# echo (꺄아) 개역한글: 요한이서 1장 6절\\n\\n또 사랑은 이것이니 우리가 그 계명을 좇아 행하는 것이요 계명은 이것이니 너희가 처음부터 들은 바와 같이 그 가운데서 행하라 하심이라\",\r\n\"# echo (꺄아) 개역한글: 베드로전서 4장 8절\\n\\n무엇보다도 열심으로 서로 사랑할지니 사랑은 허다한 죄를 덮느니라\",\r\n\"# echo (꺄아) 개역한글: 요한복음 15장 12~14절\\n\\n12 내 계명은 곧 내가 너희를 사랑한 것 같이 너희도 서로 사랑하라 하는 이것이니라 13 사람이 친구를 위하여 자기 목숨을 버리면 이에서 더 큰 사랑이 없나니 14 너희가 나의 명하는 대로 행하면 곧 나의 친구라\",\r\n\"# echo (꺄아) 개역한글: 마태복음 6장 12절\\n\\n우리가 우리에게 죄 지은 자를 사하여 준 것 같이 우리 죄를 사하여 주옵시고\"\r\n]', '{\"enabled\": true}', '2025-05-25 00:08:23', '2025-06-05 22:41:02'),
(20, '18396628723103362', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"23:50\"]', '[\"# 주간채팅순위\"]', NULL, '2025-05-25 00:08:23', '2025-06-05 22:41:02'),
(21, '18396628723103362', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"09:00\"]', '[\"# [매일성경]\"]', '{\"enabled\": true}', '2025-05-25 00:08:23', '2025-06-05 22:41:02'),
(22, '18396628723103362', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"12:00\"]', '[\"# [오늘의 성경묵상]\"]', '{\"enabled\": true}', '2025-05-25 00:08:23', '2025-06-05 22:41:02'),
(23, '18396628723103362', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"07:30\"]', '[\"# [자동응답] 오늘날씨\"]', NULL, '2025-05-25 00:08:23', '2025-06-05 22:41:02'),
(24, '18396628723103362', 'LOA.i', '[\"금\"]', '[\"18:00\"]', '[\r\n\"# echo (굿) \\n\\n즐거운 주말 되세요.\",\r\n\"# echo (굿) \\n\\n한 주 동안 수고 많으셨습니다. 편안한 주말 보내세요.\",\r\n\"# echo (굿) \\n\\n충분히 쉬시고 행복한 주말 보내시길 바랍니다!\",\r\n\"# echo (굿) \\n\\n행복이 가득한 주말 되세요.\",\r\n\"# echo (굿) \\n\\n따뜻한 햇살처럼 편안하고 기분 좋은 주말 되세요 🌿\",\r\n\"# echo (굿) \\n\\n좋아하는 것들로 가득 채운 행복한 주말 되시길 바래요!\",\r\n\"# echo (굿) \\n\\n주말엔 힐링 가득! 충전 잘 하시고 멋진 한 주 준비하세요 🔋\",\r\n\"# echo (굿) \\n\\n이번 주말엔 웃음꽃이 가득 피어나길 바랍니다 😄\",\r\n\"# echo (굿) \\n\\n행복은 주말에 두 배! 즐겁고 신나는 시간 보내세요 ✨\",\r\n\"# echo (굿) \\n\\n좋은 사람들과 멋진 주말 만드시고 활력 충전하세요!\"\r\n]', '{\"enabled\": true}', '2025-05-25 00:08:23', '2025-06-05 22:41:02'),
(25, '18263272052425167', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"23:50\"]', '[\"# 주간채팅순위\"]', NULL, '2025-05-25 00:10:25', '2025-06-05 22:41:02'),
(26, '18263272052425167', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"09:00\"]', '[\"# [매일성경]\"]', '{\"enabled\": true}', '2025-05-25 00:10:25', '2025-06-05 22:41:02'),
(27, '18263272052425167', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"12:00\"]', '[\"# [오늘의 성경묵상]\"]', '{\"enabled\": true}', '2025-05-25 00:10:25', '2025-06-05 22:41:02'),
(28, '18263272052425167', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"07:30\"]', '[\"# [자동응답] 오늘날씨\"]', NULL, '2025-05-25 00:10:25', '2025-06-05 22:41:02'),
(29, '18263272052425167', 'LOA.i', '[\"금\"]', '[\"18:00\"]', '[\r\n\"# echo (굿) \n\n즐거운 주말 되세요.\",\r\n\"# echo (굿) \n\n한 주 동안 수고 많으셨습니다. 편안한 주말 보내세요.\",\r\n\"# echo (굿) \n\n충분히 쉬시고 행복한 주말 보내시길 바랍니다!\",\r\n\"# echo (굿) \n\n행복이 가득한 주말 되세요.\",\r\n\"# echo (굿) \n\n따뜻한 햇살처럼 편안하고 기분 좋은 주말 되세요 🌿\",\r\n\"# echo (굿) \n\n좋아하는 것들로 가득 채운 행복한 주말 되시길 바래요!\",\r\n\"# echo (굿) \n\n주말엔 힐링 가득! 충전 잘 하시고 멋진 한 주 준비하세요 🔋\",\r\n\"# echo (굿) \n\n이번 주말엔 웃음꽃이 가득 피어나길 바랍니다 😄\",\r\n\"# echo (굿) \n\n행복은 주말에 두 배! 즐겁고 신나는 시간 보내세요 ✨\",\r\n\"# echo (굿) \n\n좋은 사람들과 멋진 주말 만드시고 활력 충전하세요!\"\r\n]', NULL, '2025-05-25 00:10:25', '2025-06-05 22:41:02'),
(30, '18435853038217912', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"09:00\"]', '[\"# [매일성경]\"]', '{\"enabled\": true}', '2025-05-25 00:12:08', '2025-06-05 22:41:02'),
(31, '18435853038217912', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"12:00\"]', '[\"# [오늘의 성경묵상]\"]', '{\"enabled\": true}', '2025-05-25 00:12:08', '2025-06-05 22:41:02'),
(32, '18435853038217912', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"07:30\"]', '[\"# [자동응답] 오늘날씨\"]', NULL, '2025-05-25 00:12:08', '2025-06-05 22:41:02'),
(33, '18435853038217912', 'LOA.i', '[\"금\"]', '[\"18:00\"]', '[\r\n\"# echo (굿) \\n\\n즐거운 주말 되세요.\",\r\n\"# echo (굿) \\n\\n한 주 동안 수고 많으셨습니다. 편안한 주말 보내세요.\",\r\n\"# echo (굿) \\n\\n충분히 쉬시고 행복한 주말 보내시길 바랍니다!\",\r\n\"# echo (굿) \\n\\n행복이 가득한 주말 되세요.\",\r\n\"# echo (굿) \\n\\n따뜻한 햇살처럼 편안하고 기분 좋은 주말 되세요 🌿\",\r\n\"# echo (굿) \\n\\n좋아하는 것들로 가득 채운 행복한 주말 되시길 바래요!\",\r\n\"# echo (굿) \\n\\n주말엔 힐링 가득! 충전 잘 하시고 멋진 한 주 준비하세요 🔋\",\r\n\"# echo (굿) \\n\\n이번 주말엔 웃음꽃이 가득 피어나길 바랍니다 😄\",\r\n\"# echo (굿) \\n\\n행복은 주말에 두 배! 즐겁고 신나는 시간 보내세요 ✨\",\r\n\"# echo (굿) \\n\\n좋은 사람들과 멋진 주말 만드시고 활력 충전하세요!\"\r\n]', NULL, '2025-05-25 00:12:08', '2025-06-05 22:41:02'),
(34, '18275476525650435', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"17:00\"]', '[\r\n\"# echo (꺄아) 개역한글\n\n새 계명을 너희에게 주노니 서로 사랑하라 내가 너희를 사랑한 것 같이 너희도 서로 사랑하라 - 요 13:34\",\r\n\"# echo (꺄아) 개역한글\n\n우리가 형제를 사랑함으로 사망에서 옮겨 생명으로 들어간 줄을 알거니와 사랑치 아니하는 자는 사망에 거하느니라 - 요일 3:14\",\r\n\"# echo (꺄아) 개역한글\n\n영생은 곧 유일하신 참 하나님과 그의 보내신 자 예수 그리스도를 아는 것이니이다 - 요 17:3\",\r\n\"# echo (꺄아) 개역한글\n\n사랑하는 자들아 우리가 서로 사랑하자 사랑은 하나님께 속한 것이니 - 요일 4:7~8\",\r\n\"# echo (꺄아) 개역한글\n\n피차 사랑의 빚 외에는 아무에게든지 아무 빚도 지지 말라 남을 사랑하는 자는 율법을 다 이루었느니라 - 롬 13:8\",\r\n\"# echo (꺄아) 개역한글\n\n사랑은 이웃에게 악을 행하지 아니하나니 그러므로 사랑은 율법의 완성이니라 - 롬 13:10\",\r\n\"# echo (꺄아) 개역한글\n\n온 율법은 네 이웃 사랑하기를 네 몸 같이 하라 하신 한 말씀에 이루었나니 - 갈 5:14\",\r\n\"# echo (꺄아) 개역한글\n\n너희가 진리를 순종함으로 너희 영혼을 깨끗하게 하여 거짓이 없이 형제를 사랑하기에 이르렀으니 마음으로 뜨겁게 피차 사랑하라 - 벧전 1:22\",\r\n\"# echo (꺄아) 개역한글\n\n저가 빛 가운데 계신 것 같이 우리도 빛 가운데 행하면 우리가 서로 사귐이 있고 그 아들 예수의 피가 우리를 모든 죄에서 깨끗하게 하실 것이요 - 요일 1:7\",\r\n\"# echo (꺄아) 개역한글\n\n미움은 다툼을 일으켜도 사랑은 모든 허물을 가리느니라 - 잠 10:12\",\r\n\"# echo (꺄아) 개역한글\n\n내가 내게 있는 모든 것으로 구제하고 또 내 몸을 불사르게 내어 줄지라도 사랑이 없으면 내게 아무 유익이 없느니라 - 고전 13:3\",\r\n\"# echo (꺄아) 개역한글\n\n이 모든 것 위에 사랑을 더하라 이는 온전하게 매는 띠니라 - 골 3:14\",\r\n\"# echo (꺄아) 개역한글\n\n7 사랑하는 자들아 우리가 서로 사랑하자 사랑은 하나님께 속한 것이니 사랑하는 자마다 하나님께로 나서 하나님을 알고 8 사랑하지 아니하는 자는 하나님을 알지 못하나니 이는 하나님은 사랑이심이라 - 요일 4:7~8\",\r\n\"# echo (꺄아) 개역한글\n\n10 내가 아버지의 계명을 지켜 그의 사랑 안에 거하는 것 같이 너희도 내 계명을 지키면 내 사랑 안에 거하리라 11 내가 이것을 너희에게 이름은 내 기쁨이 너희 안에 있어 너희 기쁨을 충만하게 하려 함이니라 12 내 계명은 곧 내가 너희를 사랑한 것 같이 너희도 서로 사랑하라 하는 이것이니라 - 요 15:10~12\",\r\n\"# echo (꺄아) 개역한글\n\n또 사랑은 이것이니 우리가 그 계명을 좇아 행하는 것이요 계명은 이것이니 너희가 처음부터 들은 바와 같이 그 가운데서 행하라 하심이라 - 요이 1:6\",\r\n\"# echo (꺄아) 개역한글\n\n무엇보다도 열심으로 서로 사랑할지니 사랑은 허다한 죄를 덮느니라 - 벧전 4:8\",\r\n\"# echo (꺄아) 개역한글\n\n12 내 계명은 곧 내가 너희를 사랑한 것 같이 너희도 서로 사랑하라 하는 이것이니라 13 사람이 친구를 위하여 자기 목숨을 버리면 이에서 더 큰 사랑이 없나니 14 너희가 나의 명하는 대로 행하면 곧 나의 친구라 - 요 15:12~14\",\r\n\"# echo (꺄아) 개역한글\n\n우리가 우리에게 죄 지은 자를 사하여 준 것 같이 우리 죄를 사하여 주옵시고 - 마 6:12\"\r\n]', NULL, '2025-05-25 00:13:28', '2025-06-05 22:41:02'),
(35, '18275476525650435', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"09:00\"]', '[\"# [매일성경]\"]', '{\"enabled\": true}', '2025-05-25 00:13:28', '2025-06-05 22:41:02'),
(36, '18275476525650435', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"12:00\"]', '[\"# [오늘의 성경묵상]\"]', '{\"enabled\": true}', '2025-05-25 00:13:28', '2025-06-05 22:41:02'),
(37, '18275476525650435', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"07:30\"]', '[\"# [자동응답] 오늘날씨\"]', NULL, '2025-05-25 00:13:28', '2025-06-05 22:41:02'),
(38, '18275476525650435', 'LOA.i', '[\"금\"]', '[\"18:00\"]', '[\r\n\"# echo (굿) \n\n즐거운 주말 되세요.\",\r\n\"# echo (굿) \n\n한 주 동안 수고 많으셨습니다. 편안한 주말 보내세요.\",\r\n\"# echo (굿) \n\n충분히 쉬시고 행복한 주말 보내시길 바랍니다!\",\r\n\"# echo (굿) \n\n행복이 가득한 주말 되세요.\",\r\n\"# echo (굿) \n\n따뜻한 햇살처럼 편안하고 기분 좋은 주말 되세요 🌿\",\r\n\"# echo (굿) \n\n좋아하는 것들로 가득 채운 행복한 주말 되시길 바래요!\",\r\n\"# echo (굿) \n\n주말엔 힐링 가득! 충전 잘 하시고 멋진 한 주 준비하세요 🔋\",\r\n\"# echo (굿) \n\n이번 주말엔 웃음꽃이 가득 피어나길 바랍니다 😄\",\r\n\"# echo (굿) \n\n행복은 주말에 두 배! 즐겁고 신나는 시간 보내세요 ✨\",\r\n\"# echo (굿) \n\n좋은 사람들과 멋진 주말 만드시고 활력 충전하세요!\"\r\n]', NULL, '2025-05-25 00:13:28', '2025-06-05 22:41:02'),
(39, '401611339715053', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"09:00\"]', '[\"# [매일성경]\"]', '{\"enabled\": true}', '2025-05-25 00:15:56', '2025-06-05 22:41:02'),
(40, '401611339715053', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"07:30\"]', '[\"# [자동응답] 오늘날씨\"]', NULL, '2025-05-25 00:15:56', '2025-06-05 22:41:02'),
(41, '425089245740374', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"07:30\"]', '[\"# 날씨 화도읍\"]', NULL, '2025-05-25 00:17:01', '2025-06-05 22:41:02'),
(42, '425089245740374', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"09:00\"]', '[\"# [매일성경]\"]', '{\"enabled\": true}', '2025-05-25 00:17:01', '2025-06-05 22:41:02'),
(43, '425089245740374', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"11:00\"]', '[\"# [오늘의 성경묵상]\"]', '{\"enabled\": true}', '2025-05-25 00:17:01', '2025-06-05 22:41:02'),
(44, '18389053489704706', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\"]', '[\"10:40\"]', '[\"# [블룸버그 오늘의 5가지 이슈]\"]', NULL, '2025-05-25 00:18:42', '2025-06-05 22:41:02'),
(45, '18389053489704706', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"일\",\"토\"]', '[\"08:50\",\"17:50\"]', '[\"# 환율\"]', NULL, '2025-05-25 00:18:42', '2025-06-05 22:41:02'),
(46, '18389053489704706', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\"]', '[\"15:45\"]', '[\"# echo (굿) 국내 주식 정규장 마감. 모두 수고하셨습니다.\"]', NULL, '2025-05-25 00:18:42', '2025-06-05 22:41:02'),
(47, '18389053489704706', 'LOA.i', '[\"월\",\"수\",\"금\"]', '[\"10:00\"]', '[\"# [오늘의 투자격언] 리밸런싱\"]', NULL, '2025-05-25 00:18:42', '2025-06-05 22:41:02'),
(48, '18389053489704706', 'LOA.i', '[\"화\",\"목\"]', '[\"10:00\"]', '[\"# [오늘의 투자격언] 위험관리\"]', NULL, '2025-05-25 00:18:42', '2025-06-05 22:41:02'),
(49, '18389053489704706', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\"]', '[\"15:00\"]', '[\"# [오늘의 투자격언]\"]', NULL, '2025-05-25 00:18:42', '2025-06-05 22:41:02'),
(50, '18389053489704706', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\"]', '[\"15:45\"]', '[\"# 장마감\"]', NULL, '2025-05-25 00:18:42', '2025-06-05 22:41:02'),
(51, '18389053489704706', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\"]', '[\"20:00\"]', '[\"# [오늘의 투자격언]\"]', NULL, '2025-05-25 00:18:42', '2025-06-05 22:41:02'),
(52, '18389053489704706', 'LOA.i', '[\"금\"]', '[\"18:00\"]', '[\r\n\"# echo (굿) \n\n즐거운 주말 되세요.\",\r\n\"# echo (굿) \n\n한 주 동안 수고 많으셨습니다. 편안한 주말 보내세요.\",\r\n\"# echo (굿) \n\n충분히 쉬시고 행복한 주말 보내시길 바랍니다!\",\r\n\"# echo (굿) \n\n행복이 가득한 주말 되세요.\",\r\n\"# echo (굿) \n\n따뜻한 햇살처럼 편안하고 기분 좋은 주말 되세요 🌿\",\r\n\"# echo (굿) \n\n좋아하는 것들로 가득 채운 행복한 주말 되시길 바래요!\",\r\n\"# echo (굿) \n\n주말엔 힐링 가득! 충전 잘 하시고 멋진 한 주 준비하세요 🔋\",\r\n\"# echo (굿) \n\n이번 주말엔 웃음꽃이 가득 피어나길 바랍니다 😄\",\r\n\"# echo (굿) \n\n행복은 주말에 두 배! 즐겁고 신나는 시간 보내세요 ✨\",\r\n\"# echo (굿) \n\n좋은 사람들과 멋진 주말 만드시고 활력 충전하세요!\"\r\n]', NULL, '2025-05-25 00:18:42', '2025-06-05 22:41:02'),
(53, '18389053489704706', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"08:05\"]', '[\"# [자동응답] 오늘날씨\"]', NULL, '2025-05-25 00:18:42', '2025-06-05 22:41:02'),
(54, '18358994267380807', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"23:51\",\"11:51\"]', '[\"# 최근 대화 요약!\"]', NULL, '2025-05-25 00:19:43', '2025-06-05 22:41:02'),
(55, '18358994267380807', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"23:55\"]', '[\"# 주간채팅순위\"]', NULL, '2025-05-25 00:19:43', '2025-06-05 22:41:02'),
(56, '18358994267380807', 'LOA.i', '[\"금\"]', '[\"18:00\"]', '[\r\n\"# echo (굿) \n\n즐거운 주말 되세요.\",\r\n\"# echo (굿) \n\n한 주 동안 수고 많으셨습니다. 편안한 주말 보내세요.\",\r\n\"# echo (굿) \n\n충분히 쉬시고 행복한 주말 보내시길 바랍니다!\",\r\n\"# echo (굿) \n\n행복이 가득한 주말 되세요.\",\r\n\"# echo (굿) \n\n따뜻한 햇살처럼 편안하고 기분 좋은 주말 되세요 🌿\",\r\n\"# echo (굿) \n\n좋아하는 것들로 가득 채운 행복한 주말 되시길 바래요!\",\r\n\"# echo (굿) \n\n주말엔 힐링 가득! 충전 잘 하시고 멋진 한 주 준비하세요 🔋\",\r\n\"# echo (굿) \n\n이번 주말엔 웃음꽃이 가득 피어나길 바랍니다 😄\",\r\n\"# echo (굿) \n\n행복은 주말에 두 배! 즐겁고 신나는 시간 보내세요 ✨\",\r\n\"# echo (굿) \n\n좋은 사람들과 멋진 주말 만드시고 활력 충전하세요!\"\r\n]', NULL, '2025-05-25 00:19:43', '2025-06-05 22:41:02'),
(57, '18436882630596864', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\"]', '[\"10:40\"]', '[\"# [블룸버그 오늘의 5가지 이슈]\"]', NULL, '2025-05-25 00:20:49', '2025-06-05 22:41:02'),
(58, '18436882630596864', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"일\",\"토\"]', '[\"08:50\",\"17:50\"]', '[\"# 환율\"]', NULL, '2025-05-25 00:20:49', '2025-06-05 22:41:02'),
(59, '18436882630596864', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\"]', '[\"10:00\",\"18:00\"]', '[\"# [오늘의 투자격언]\"]', NULL, '2025-05-25 00:20:49', '2025-06-05 22:41:02'),
(60, '18436882630596864', 'LOA.i', '[\"금\"]', '[\"18:00\"]', '[\r\n\"# echo (굿) \n\n즐거운 주말 되세요.\",\r\n\"# echo (굿) \n\n한 주 동안 수고 많으셨습니다. 편안한 주말 보내세요.\",\r\n\"# echo (굿) \n\n충분히 쉬시고 행복한 주말 보내시길 바랍니다!\",\r\n\"# echo (굿) \n\n행복이 가득한 주말 되세요.\",\r\n\"# echo (굿) \n\n따뜻한 햇살처럼 편안하고 기분 좋은 주말 되세요 🌿\",\r\n\"# echo (굿) \n\n좋아하는 것들로 가득 채운 행복한 주말 되시길 바래요!\",\r\n\"# echo (굿) \n\n주말엔 힐링 가득! 충전 잘 하시고 멋진 한 주 준비하세요 🔋\",\r\n\"# echo (굿) \n\n이번 주말엔 웃음꽃이 가득 피어나길 바랍니다 😄\",\r\n\"# echo (굿) \n\n행복은 주말에 두 배! 즐겁고 신나는 시간 보내세요 ✨\",\r\n\"# echo (굿) \n\n좋은 사람들과 멋진 주말 만드시고 활력 충전하세요!\"\r\n]', NULL, '2025-05-25 00:20:49', '2025-06-05 22:41:02'),
(61, '18436882630596864', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"07:30\"]', '[\"# [자동응답] 오늘날씨\"]', NULL, '2025-05-25 00:20:49', '2025-06-05 22:41:02'),
(62, '18317005864945719', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"00:05\"]', '[\"# 채팅순위\"]', NULL, '2025-05-25 00:22:48', '2025-06-05 22:41:02'),
(63, '18317005864945719', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"23:55\"]', '[\"# 주간채팅순위\"]', NULL, '2025-05-25 00:22:48', '2025-06-05 22:41:02'),
(64, '18317005864945719', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"23:56\"]', '[\"# 오늘의 대화 요약!\"]', '{\"enabled\": true}', '2025-05-25 00:22:48', '2025-06-05 22:41:02'),
(65, '18317005864945719', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"08:55\",\"11:55\",\"14:55\",\"17:55\",\"20:55\",\"23:55\",\"02:55\",\"05:55\"]', '[\"# 최근 대화 요약!\"]', NULL, '2025-05-25 00:22:48', '2025-06-05 22:41:02'),
(66, '18317005864945719', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\"]', '[\"10:40\"]', '[\"# [블룸버그 오늘의 5가지 이슈]\"]', NULL, '2025-05-25 00:22:48', '2025-06-05 22:41:02'),
(67, '18317005864945719', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"일\",\"토\"]', '[\"08:50\",\"17:50\"]', '[\"# 환율\"]', NULL, '2025-05-25 00:22:48', '2025-06-05 22:41:02'),
(68, '18317005864945719', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\"]', '[\"15:45\"]', '[\"# 장마감\"]', NULL, '2025-05-25 00:22:48', '2025-06-05 22:41:02'),
(69, '18317005864945719', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\"]', '[\"15:45\"]', '[\"# echo (굿) 국내 주식 정규장 마감. 모두 수고하셨습니다.\"]', NULL, '2025-05-25 00:22:48', '2025-06-05 22:41:02'),
(70, '18317005864945719', 'LOA.i', '[\"금\"]', '[\"18:00\"]', '[\r\n\"# echo (굿) \n\n즐거운 주말 되세요.\",\r\n\"# echo (굿) \n\n한 주 동안 수고 많으셨습니다. 편안한 주말 보내세요.\",\r\n\"# echo (굿) \n\n충분히 쉬시고 행복한 주말 보내시길 바랍니다!\",\r\n\"# echo (굿) \n\n행복이 가득한 주말 되세요.\",\r\n\"# echo (굿) \n\n따뜻한 햇살처럼 편안하고 기분 좋은 주말 되세요 🌿\",\r\n\"# echo (굿) \n\n좋아하는 것들로 가득 채운 행복한 주말 되시길 바래요!\",\r\n\"# echo (굿) \n\n주말엔 힐링 가득! 충전 잘 하시고 멋진 한 주 준비하세요 🔋\",\r\n\"# echo (굿) \n\n이번 주말엔 웃음꽃이 가득 피어나길 바랍니다 😄\",\r\n\"# echo (굿) \n\n행복은 주말에 두 배! 즐겁고 신나는 시간 보내세요 ✨\",\r\n\"# echo (굿) \n\n좋은 사람들과 멋진 주말 만드시고 활력 충전하세요!\"\r\n]', NULL, '2025-05-25 00:22:48', '2025-06-05 22:41:02'),
(71, '18398338829933617', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\"]', '[\"23:45\"]', '[\"# 오늘의 대화 요약!\"]', '{\"enabled\": true, \"config\": {\"language\": \"auto\", \"gender\": \"F\", \"voice\": \"auto\", \"intro\": [\"음성으로 녹음해 보았어요! 🧏\", \"내용을 녹음해 보았어요. 🔔\", \"직접 읽어봤어요! 🎙️\", \"이 내용을 들려드릴게요. 🎧\", \"음성으로 정리해 드릴게요! 📋\", \"제가 읽어볼게요~ 🎤\", \"짧게 녹음해봤어요! 🎙️\", \"핵심만 콕! 읽어드릴게요. 🧠\", \"요약된 내용을 들어보세요. 📝\", \"들어보는 게 더 편하죠? 👂\", \"말로 전해드릴게요! 🗣️\"]}}', '2025-05-25 00:26:10', '2025-06-05 22:41:02'),
(72, '18398338829933617', 'LOA.i', '[\"월\",\"화\",\"수\",\"목\",\"금\",\"토\",\"일\"]', '[\"23:44\",\"05:44\",\"11:44\",\"17:44\"]', '[\"# 최근 대화 요약!\"]', NULL, '2025-05-25 00:26:10', '2025-06-05 22:41:02');

--
-- 덤프된 테이블의 인덱스
--

--
-- 테이블의 인덱스 `kb_room_schedules`
--
ALTER TABLE `kb_room_schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `room_id` (`room_id`);

--
-- 덤프된 테이블의 AUTO_INCREMENT
--

--
-- 테이블의 AUTO_INCREMENT `kb_room_schedules`
--
ALTER TABLE `kb_room_schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '스케줄 고유키', AUTO_INCREMENT=73;

--
-- 덤프된 테이블의 제약사항
--

--
-- 테이블의 제약사항 `kb_room_schedules`
--
ALTER TABLE `kb_room_schedules`
  ADD CONSTRAINT `kb_room_schedules_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `kb_rooms` (`room_id`);
COMMIT;
