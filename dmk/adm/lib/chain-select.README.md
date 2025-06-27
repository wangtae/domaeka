# 도매까 계층별 선택박스 공통 라이브러리

총판-대리점-지점 체인 선택박스를 위한 공통 라이브러리입니다. 각 계층의 관리자가 접속함에 따라 자동으로 표시되는 선택박스가 달라집니다.

## 📁 파일 구조

```
dmk/adm/
├── lib/
│   └── chain-select.lib.php      # PHP 공통 함수
├── js/
│   └── chain-select.js           # JavaScript 라이브러리
├── css/
│   └── chain-select.css          # CSS 스타일
└── [각 모듈]/
    ├── get_agencies.php          # 대리점 목록 AJAX 엔드포인트
    └── get_branches.php          # 지점 목록 AJAX 엔드포인트
```

## 🚀 기본 사용법

### 1. 라이브러리 포함

```php
<?php
// 공통 체인 선택박스 라이브러리 포함
include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');

// 에셋 포함 (HTML head 부분에)
echo dmk_include_chain_select_assets();
?>
```

### 2. 선택박스 렌더링

```php
<?php
// 기본 사용 (총판-대리점-지점 모두)
echo dmk_render_chain_select();

// 옵션 설정
echo dmk_render_chain_select([
    'page_type' => DMK_CHAIN_SELECT_FULL,
    'current_values' => [
        'sdt_id' => $_GET['sdt_id'] ?? '',
        'sag_id' => $_GET['sag_id'] ?? '',
        'sbr_id' => $_GET['sbr_id'] ?? ''
    ]
]);
?>
```

## 📋 페이지 유형별 설정

### 1. 총판만 선택 (DMK_CHAIN_SELECT_DISTRIBUTOR_ONLY)
```php
echo dmk_render_chain_select([
    'page_type' => DMK_CHAIN_SELECT_DISTRIBUTOR_ONLY
]);
```

### 2. 총판-대리점 선택 (DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY)
```php
echo dmk_render_chain_select([
    'page_type' => DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY
]);
```

### 3. 총판-대리점-지점 선택 (DMK_CHAIN_SELECT_FULL)
```php
echo dmk_render_chain_select([
    'page_type' => DMK_CHAIN_SELECT_FULL
]);
```

## 🔐 관리자 권한별 표시 규칙

| 관리자 계층 | 총판 선택박스 | 대리점 선택박스 | 지점 선택박스 | 비고 |
|------------|-------------|---------------|-------------|------|
| **본사** | ✅ 전체 표시 | ✅ 전체 표시 | ✅ 전체 표시 | 모든 선택박스 활성 |
| **총판** | 🔒 고정 (자신) | ✅ 소속 대리점 | ✅ 소속 지점 | 총판은 읽기전용 |
| **대리점** | 🔒 고정 (소속) | 🔒 고정 (자신) | ✅ 소속 지점 | 총판/대리점 읽기전용 |
| **지점** | 🔒 고정 (소속) | 🔒 고정 (소속) | 🔒 고정 (자신) | 모든 선택박스 읽기전용 |

### 페이지 유형별 표시 제한

**DMK_CHAIN_SELECT_DISTRIBUTOR_ONLY**
- 대리점 관리자: 선택박스 표시 안함
- 지점 관리자: 선택박스 표시 안함

**DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY**
- 지점 관리자: 선택박스 표시 안함

**DMK_CHAIN_SELECT_FULL**
- 모든 관리자: 권한에 따라 표시

## ⚙️ 상세 옵션 설정

```php
echo dmk_render_chain_select([
    // 기본 설정
    'dmk_auth' => dmk_get_admin_auth(),
    'page_type' => DMK_CHAIN_SELECT_FULL,
    
    // 현재 선택값
    'current_values' => [
        'sdt_id' => $_GET['sdt_id'] ?? '',
        'sag_id' => $_GET['sag_id'] ?? '',
        'sbr_id' => $_GET['sbr_id'] ?? ''
    ],
    
    // 폼 설정
    'form_id' => 'fsearch',
    'auto_submit' => true,
    
    // AJAX 엔드포인트
    'ajax_endpoints' => [
        'agencies' => './get_agencies.php',
        'branches' => './get_branches.php'
    ],
    
    // CSS 클래스
    'css_classes' => [
        'select' => 'frm_input',
        'label' => 'sound_only'
    ],
    
    // 라벨 텍스트
    'labels' => [
        'distributor' => '총판 선택',
        'agency' => '대리점 선택',
        'branch' => '지점 선택'
    ],
    
    // 플레이스홀더
    'placeholders' => [
        'distributor' => '전체 총판',
        'agency' => '전체 대리점',
        'branch' => '전체 지점'
    ],
    
    // 디버그 모드
    'debug' => false
]);
```

## 🔄 AJAX 엔드포인트 설정

각 모듈 폴더에 다음 파일들을 생성해야 합니다:

### get_agencies.php
```php
<?php
include_once '../../../adm/_common.php';
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

header('Content-Type: application/json');

$dmk_auth = dmk_get_admin_auth();
$dt_id = isset($_GET['dt_id']) ? clean_xss_tags($_GET['dt_id']) : '';

$agencies = [];

if ($dmk_auth['is_super'] || ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR && $dmk_auth['dt_id'] == $dt_id)) {
    // 대리점 목록 조회 로직
    $ag_sql = "SELECT a.ag_id, m.mb_nick AS ag_name 
               FROM dmk_agency a
               JOIN {$g5['member_table']} m ON a.ag_id = m.mb_id
               WHERE a.dt_id = '".sql_escape_string($dt_id)."' AND a.ag_status = 1 
               ORDER BY m.mb_nick ASC";
    $ag_res = sql_query($ag_sql);
    while($ag_row = sql_fetch_array($ag_res)) {
        $agencies[] = [
            'id' => $ag_row['ag_id'],
            'name' => $ag_row['ag_name']
        ];
    }
}

echo json_encode($agencies);
?>
```

### get_branches.php
```php
<?php
include_once '../../../adm/_common.php';
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

header('Content-Type: application/json');

$dmk_auth = dmk_get_admin_auth();
$ag_id = isset($_GET['ag_id']) ? clean_xss_tags($_GET['ag_id']) : '';

$branches = [];

if ($dmk_auth['is_super'] || 
    ($dmk_auth['mb_type'] == DMK_MB_TYPE_DISTRIBUTOR) ||
    ($dmk_auth['mb_type'] == DMK_MB_TYPE_AGENCY && $dmk_auth['ag_id'] == $ag_id)
) {
    // 지점 목록 조회 로직
    $br_sql = "SELECT b.br_id, m.mb_nick AS br_name 
               FROM dmk_branch b
               JOIN {$g5['member_table']} m ON b.br_id = m.mb_id
               WHERE b.ag_id = '".sql_escape_string($ag_id)."' AND b.br_status = 1 
               ORDER BY m.mb_nick ASC";
    $br_res = sql_query($br_sql);
    while($br_row = sql_fetch_array($br_res)) {
        $branches[] = [
            'id' => $br_row['br_id'],
            'name' => $br_row['br_name']
        ];
    }
}

echo json_encode($branches);
?>
```

## 🎨 CSS 커스터마이징

기본 CSS 클래스들을 오버라이드하여 스타일을 커스터마이징할 수 있습니다:

```css
/* 선택박스 컨테이너 */
.dmk-chain-select-container {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}

/* 선택박스 스타일 */
.dmk-chain-select {
    min-width: 150px;
    padding: 5px 8px;
    border: 1px solid #ddd;
    border-radius: 3px;
}

/* 로딩 상태 */
.dmk-chain-select.loading {
    opacity: 0.6;
    cursor: wait;
}

/* 읽기 전용 */
.dmk-chain-select[readonly] {
    background-color: #f5f5f5;
    cursor: not-allowed;
}
```

## 🔧 JavaScript API

JavaScript에서 직접 제어하려면:

```javascript
// 인스턴스 생성
const chainSelect = new DmkChainSelect({
    distributorSelectId: 'sdt_id',
    agencySelectId: 'sag_id',
    branchSelectId: 'sbr_id',
    agencyEndpoint: './get_agencies.php',
    branchEndpoint: './get_branches.php',
    autoSubmit: false,
    debug: true
});

// 현재 선택값 가져오기
const values = chainSelect.getValues();
console.log(values); // {distributor: 'dt001', agency: 'ag001', branch: 'br001'}

// 선택값 설정
chainSelect.setValues({
    distributor: 'dt002',
    agency: 'ag002'
});
```

## 📝 실제 사용 예시

### admin_list.php (관리자 목록)
```php
<?php
include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');
echo dmk_include_chain_select_assets();

// 총판-대리점-지점 모두 사용
echo dmk_render_chain_select([
    'page_type' => DMK_CHAIN_SELECT_FULL,
    'current_values' => [
        'sdt_id' => $_GET['sdt_id'] ?? '',
        'sag_id' => $_GET['sag_id'] ?? '',
        'sbr_id' => $_GET['sbr_id'] ?? ''
    ]
]);
?>
```

### branch_list.php (지점 목록)
```php
<?php
include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');
echo dmk_include_chain_select_assets();

// 총판-대리점만 사용
echo dmk_render_chain_select([
    'page_type' => DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY,
    'current_values' => [
        'sdt_id' => $_GET['dt_id'] ?? '',
        'sag_id' => $_GET['ag_id'] ?? '',
        'sbr_id' => ''
    ],
    'ajax_endpoints' => [
        'agencies' => '../admin_manager/get_agencies.php',
        'branches' => '../admin_manager/get_branches.php'
    ]
]);
?>
```

## 🐛 트러블슈팅

### 1. 선택박스가 표시되지 않는 경우
- 관리자 권한 확인: `dmk_get_admin_auth()` 결과 확인
- 페이지 유형과 관리자 계층 매칭 확인
- JavaScript 에러 확인 (브라우저 개발자 도구)

### 2. AJAX 호출이 실패하는 경우
- 엔드포인트 파일 경로 확인
- 서버 로그 확인
- 권한 검증 로직 확인

### 3. 선택값이 복원되지 않는 경우
- `current_values` 배열 확인
- 폼 필드명 일치 확인 (sdt_id, sag_id, sbr_id)

### 4. 디버그 모드 활성화
```php
echo dmk_render_chain_select([
    'debug' => true
]);
```

브라우저 콘솔에서 `[DmkChainSelect]` 로그 확인

## 📊 성능 최적화

1. **캐싱**: 총판/대리점 목록은 세션이나 캐시에 저장하여 반복 조회 최소화
2. **지연 로딩**: 필요한 시점에만 하위 데이터 로드
3. **압축**: JavaScript/CSS 파일 압축 적용
4. **CDN**: 정적 에셋을 CDN으로 서빙

## 🔒 보안 고려사항

1. **권한 검증**: 모든 AJAX 엔드포인트에서 관리자 권한 재검증
2. **입력 검증**: SQL 인젝션 방지를 위한 입력값 검증
3. **XSS 방지**: 출력값 이스케이프 처리
4. **CSRF 방지**: 필요시 토큰 검증 추가

## 📈 확장 가능성

1. **다국어 지원**: 라벨/플레이스홀더 다국어 처리
2. **테마 지원**: 다양한 UI 테마 제공
3. **모바일 최적화**: 터치 인터페이스 개선
4. **접근성**: 스크린 리더 지원 강화 