# DMK Chain Select Library

도매까 프로젝트의 계층형 선택박스(총판-대리점-지점) 공통 라이브러리입니다.

## 📋 주요 기능

1. **총판-대리점-지점 선택박스를 출력**할 수 있습니다.
2. **다양한 페이지 타입 지원**: 총판만, 총판-대리점, 총판-대리점-지점
3. **로그인한 관리자 계층에 따른 자동 제어**
4. **페이지 모드별 렌더링**: 목록(선택박스), 등록폼(선택박스), 수정폼(readonly input)
5. **보안**: 하위 계층에서 상위 계층 ID 정보 숨김
6. **권한 기반 접근 제어**

## 🔧 기본 사용법

### 라이브러리 포함
```php
include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');
```

### 간단한 사용 (편의 함수)

#### 1. 목록 페이지용
```php
// 기본 사용 - 총판-대리점-지점 모두 표시
echo dmk_chain_select_for_list([
    'sdt_id' => $sdt_id,
    'sag_id' => $sag_id, 
    'sbr_id' => $sbr_id
]);

// 총판-대리점만 표시
echo dmk_chain_select_for_list([
    'sdt_id' => $sdt_id,
    'sag_id' => $sag_id
], DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY);
```

#### 2. 등록 폼용
```php
// 새 항목 등록 시 - 선택박스로 표시
echo dmk_chain_select_for_form_new([], DMK_CHAIN_SELECT_FULL, [
    'form_id' => 'myform',
    'field_names' => [
        'distributor' => 'dt_id',
        'agency' => 'ag_id', 
        'branch' => 'br_id'
    ]
]);
```

#### 3. 수정 폼용
```php
// 기존 항목 수정 시 - readonly input으로 표시
echo dmk_chain_select_for_form_edit([
    'sdt_id' => $existing_dt_id,
    'sag_id' => $existing_ag_id,
    'sbr_id' => $existing_br_id
]);
```

## 🎛️ 고급 사용법

### 직접 dmk_render_chain_select 사용
```php
echo dmk_render_chain_select([
    'page_type' => DMK_CHAIN_SELECT_FULL,
    'page_mode' => DMK_CHAIN_MODE_LIST,
    'current_values' => [
        'sdt_id' => $sdt_id,
        'sag_id' => $sag_id,
        'sbr_id' => $sbr_id
    ],
    'field_names' => [
        'distributor' => 'sdt_id',
        'agency' => 'sag_id',
        'branch' => 'sbr_id'
    ],
    'placeholders' => [
        'distributor' => '전체 총판',
        'agency' => '전체 대리점', 
        'branch' => '전체 지점'
    ],
    'form_id' => 'fsearch',
    'auto_submit' => true
]);
```

## 📊 계층별 권한 제어

### 로그인 계층에 따른 자동 선택박스 제어

| 로그인 계층 | DMK_CHAIN_SELECT_FULL 요청 시 표시되는 선택박스 |
|-------------|----------------------------------------------|
| **본사** | 총판 + 대리점 + 지점 |
| **총판** | 대리점 + 지점 (총판은 자신으로 고정) |
| **대리점** | 지점 (총판-대리점은 자신으로 고정) |  
| **지점** | 선택박스 없음 (모든 값 고정) |

### 수정 폼에서의 추가 제어

수정 폼(`DMK_CHAIN_MODE_FORM_EDIT`)에서는 모든 선택박스가 readonly input으로 표시되어 무단 변경을 방지합니다.

## 🔒 보안 특징

### 상위 계층 정보 보호

하위 계층 관리자는 상위 계층의 ID 정보를 볼 수 없습니다:

- **본사**: 모든 정보 표시 (`총판명 (DT001)`)
- **총판**: ID 숨김 (`총판명`)  
- **대리점**: ID 숨김 (`대리점명`)
- **지점**: ID 숨김 (`지점명`)

## 📝 상수 정의

### 페이지 타입
```php
DMK_CHAIN_SELECT_DISTRIBUTOR_ONLY    // 총판만
DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY  // 총판-대리점 
DMK_CHAIN_SELECT_FULL               // 총판-대리점-지점
```

### 페이지 모드
```php
DMK_CHAIN_MODE_LIST      // 목록 페이지 - 선택박스
DMK_CHAIN_MODE_FORM_NEW  // 등록 폼 - 선택박스  
DMK_CHAIN_MODE_FORM_EDIT // 수정 폼 - readonly input
```

## 🎨 CSS 클래스

생성되는 HTML 요소들의 CSS 클래스:

- `.dmk-chain-select-container`: 컨테이너
- `.dmk-chain-select`: 선택박스
- `.dmk-chain-select-label`: 라벨 (show_labels=true일 때)

## 🔄 AJAX 엔드포인트

체인 선택박스는 다음 AJAX 엔드포인트를 사용합니다:

- **대리점 목록**: `/dmk/adm/_ajax/get_agencies.php?dt_id={총판ID}`
- **지점 목록**: `/dmk/adm/_ajax/get_branches.php?ag_id={대리점ID}`

## 📋 옵션 설정

### 주요 옵션들

| 옵션 | 기본값 | 설명 |
|------|--------|------|
| `page_type` | `DMK_CHAIN_SELECT_FULL` | 표시할 선택박스 범위 |
| `page_mode` | `DMK_CHAIN_MODE_LIST` | 렌더링 모드 |
| `auto_submit` | `true` (list), `false` (form) | 선택 시 자동 폼 제출 |
| `show_labels` | `false` | 라벨 표시 여부 |
| `form_id` | `'fsearch'` | 폼 ID |

### 필드명 커스터마이징
```php
'field_names' => [
    'distributor' => 'custom_dt_id',
    'agency' => 'custom_ag_id',
    'branch' => 'custom_br_id'
]
```

### 플레이스홀더 커스터마이징
```php
'placeholders' => [
    'distributor' => '총판을 선택하세요',
    'agency' => '대리점을 선택하세요',
    'branch' => '지점을 선택하세요'
]
```

## 🚀 실제 사용 예제

### 1. 주문 목록 페이지 (orderlist.php)
```php
include_once(G5_DMK_PATH.'/adm/lib/chain-select.lib.php');
echo dmk_include_chain_select_assets();

// 파라미터 처리
$sdt_id = isset($_GET['sdt_id']) ? clean_xss_tags($_GET['sdt_id']) : '';
$sag_id = isset($_GET['sag_id']) ? clean_xss_tags($_GET['sag_id']) : '';
$sbr_id = isset($_GET['sbr_id']) ? clean_xss_tags($_GET['sbr_id']) : '';

// 선택박스 출력
echo dmk_chain_select_for_list([
    'sdt_id' => $sdt_id,
    'sag_id' => $sag_id,
    'sbr_id' => $sbr_id
], DMK_CHAIN_SELECT_FULL, [
    'form_id' => 'frmorderlist'
]);
```

### 2. 지점 등록 폼 (branch_form.php)
```php
// 등록 모드 ($w == '')
if ($w == '') {
    echo dmk_chain_select_for_form_new([], DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY, [
        'form_id' => 'fbranchform',
        'field_names' => [
            'distributor' => 'dt_id',
            'agency' => 'ag_id'
        ]
    ]);
}
// 수정 모드 ($w == 'u')  
else {
    echo dmk_chain_select_for_form_edit([
        'sdt_id' => $branch['dt_id'],
        'sag_id' => $branch['ag_id']
    ], DMK_CHAIN_SELECT_DISTRIBUTOR_AGENCY);
}
```

### 3. 카테고리 관리 (categoryform.php)
```php
echo dmk_chain_select_for_form_new([
    'sdt_id' => $dmk_dt_id,
    'sag_id' => $dmk_ag_id,
    'sbr_id' => $dmk_br_id
], DMK_CHAIN_SELECT_FULL, [
    'form_id' => 'fcategoryform',
    'auto_submit' => false,
    'container_class' => 'dmk-owner-select'
]);
```

## 🐛 디버깅

### 디버그 모드 활성화
```php
echo dmk_render_chain_select([
    // ... 기타 옵션
    'debug' => true
]);
```

디버그 모드에서는 브라우저 콘솔에 다음 정보가 출력됩니다:
- 선택박스 초기화 정보
- AJAX 요청/응답 상세 로그
- 이벤트 처리 과정

## 📚 관련 파일 구조

```
dmk/adm/lib/
├── chain-select.lib.php      # 메인 라이브러리
├── chain-select.README.md    # 이 문서
└── admin.auth.lib.php        # 권한 관련 함수들

dmk/adm/js/
├── chain-select.js           # JavaScript 라이브러리
└── chain-select.css          # 스타일시트

dmk/adm/_ajax/
├── get_agencies.php          # 대리점 목록 AJAX
└── get_branches.php          # 지점 목록 AJAX
```

## ⚠️ 주의사항

1. **라이브러리 포함**: 사용 전 반드시 `chain-select.lib.php` 포함
2. **Asset 로딩**: `dmk_include_chain_select_assets()` 호출 필요
3. **권한 검증**: 페이지별로 적절한 권한 검증 수행
4. **AJAX 엔드포인트**: 필요한 AJAX 파일들이 존재하는지 확인
5. **form_edit 모드**: readonly input이므로 JavaScript 불필요

## 🔄 마이그레이션 가이드

### 기존 코드에서 새 라이브러리로 전환

#### Before (기존)
```php
// 복잡한 개별 구현
echo '<select name="sdt_id">...</select>';
// + JavaScript 이벤트 처리
// + AJAX 구현
```

#### After (신규)
```php
// 간단한 함수 호출
echo dmk_chain_select_for_list(['sdt_id' => $sdt_id]);
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

## 🔒 보안 고려사항

1. **권한 검증**: 모든 AJAX 엔드포인트에서 관리자 권한 재검증
2. **입력 검증**: SQL 인젝션 방지를 위한 입력값 검증
3. **XSS 방지**: 출력값 이스케이프 처리
4. **CSRF 방지**: 필요시 토큰 검증 추가

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
브라우저 콘솔에서 `[DmkChainSelect]` 로그 확인

## 📞 지원

문제가 발생하거나 기능 개선이 필요한 경우:
1. 코드 검토 요청
2. 새로운 페이지 타입이나 모드 추가 검토
3. 권한 규칙 수정 검토

---

**주의**: 이 라이브러리는 도매까 프로젝트의 보안 및 권한 체계와 밀접하게 연관되어 있습니다. 수정 시 충분한 테스트를 수행하세요.