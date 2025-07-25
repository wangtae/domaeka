/**
 * 도매까 계층별 선택박스 공통 라이브러리
 * 총판-대리점-지점 체인 선택박스 구현
 */

// 중복 로드 방지
if (typeof DmkChainSelect !== 'undefined') {
    console.warn('DmkChainSelect is already defined. Skipping redefinition.');
} else {

class DmkChainSelect {
    constructor(options) {
        this.options = {
            // 선택박스 ID
            distributorSelectId: 'sdt_id',
            agencySelectId: 'sag_id',
            branchSelectId: 'sbr_id',
            
            // AJAX 엔드포인트 (절대 경로)
            agencyEndpoint: '/dmk/adm/_ajax/get_agencies.php',
            branchEndpoint: '/dmk/adm/_ajax/get_branches.php',
            
            // 초기 선택값
            initialDistributor: '',
            initialAgency: '',
            initialBranch: '',
            
            // 폼 제출 여부
            autoSubmit: true,
            formId: 'fsearch',
            
            // 콜백 함수 (문자열 또는 함수)
            onDistributorChange: null,
            onAgencyChange: null,
            onBranchChange: null,
            
            // 디버그 모드
            debug: true,
            
            ...options
        };
        
        this.currentValues = {
            distributor: this.options.initialDistributor,
            agency: this.options.initialAgency,
            branch: this.options.initialBranch
        };
        
        this.init();
    }
    
    init() {
        this.log('DmkChainSelect 초기화 시작');
        
        // DOM 로드 완료 후 실행
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupEventListeners());
        } else {
            this.setupEventListeners();
        }
    }
    
    setupEventListeners() {
        const distributorSelect = document.getElementById(this.options.distributorSelectId);
        const agencySelect = document.getElementById(this.options.agencySelectId);
        const branchSelect = document.getElementById(this.options.branchSelectId);
        
        this.log('선택박스 엘리먼트 찾기 결과:');
        this.log('- 총판 선택박스:', distributorSelect ? '찾음' : '못 찾음', this.options.distributorSelectId);
        this.log('- 대리점 선택박스:', agencySelect ? '찾음' : '못 찾음', this.options.agencySelectId);
        this.log('- 지점 선택박스:', branchSelect ? '찾음' : '못 찾음', this.options.branchSelectId);
        
        if (distributorSelect) {
            this.log('총판 선택박스 이벤트 리스너 등록');
            distributorSelect.addEventListener('change', (event) => {
                this.log('총판 선택박스 change 이벤트 발생! 선택된 값:', event.target.value);
                this.onDistributorChange();
            });
        }
        
        if (agencySelect) {
            this.log('대리점 선택박스 이벤트 리스너 등록');
            agencySelect.addEventListener('change', (event) => {
                this.log('대리점 선택박스 change 이벤트 발생! 선택된 값:', event.target.value);
                this.onAgencyChange();
            });
        }
        
        if (branchSelect) {
            this.log('지점 선택박스 이벤트 리스너 등록');
            branchSelect.addEventListener('change', (event) => {
                this.log('지점 선택박스 change 이벤트 발생! 선택된 값:', event.target.value);
                this.onBranchChange();
            });
        }
        
        // 초기 로드
        this.loadInitialData();
    }
    
    loadInitialData() {
        this.log('초기 데이터 로드 시작');
        
        // 총판이 선택되어 있으면 대리점 로드
        if (this.currentValues.distributor) {
            this.log('초기 총판이 있어 대리점 로드 시도:', this.currentValues.distributor);
            this.loadAgencies(this.currentValues.distributor, () => {
                // 대리점이 선택되어 있으면 지점 로드 (총판 하위에서)
                if (this.currentValues.agency) {
                    this.log('초기 대리점이 있어 지점 로드 시도:', this.currentValues.agency);
                    this.loadBranches(this.currentValues.agency);
                }
            });
        } else if (this.currentValues.agency) { // 추가된 로직
            // 총판이 없고 대리점만 설정된 경우 (예: 대리점 관리자 로그인 시)
            this.log('총판 초기값 없이 대리점 초기값으로 지점 목록 로드 시도:', this.currentValues.agency);
            this.loadBranches(this.currentValues.agency);
        }
    }
    
    onDistributorChange() {
        const distributorSelect = document.getElementById(this.options.distributorSelectId);
        const distributorId = distributorSelect ? distributorSelect.value : '';

        this.log('총판 변경:', distributorId);

        // 총판이 변경되면 즉시 하위 선택박스들을 초기화
        this.clearSelect(this.options.agencySelectId);
        this.clearSelect(this.options.branchSelectId);

        this.currentValues.distributor = distributorId;
        this.currentValues.agency = '';
        this.currentValues.branch = '';

        if (distributorId) {
            // AJAX 요청 완료 후 콜백에서 폼 제출
            this.loadAgencies(distributorId, () => {
                // 콜백 실행
                if (this.options.onDistributorChange) {
                    this.executeCallback(this.options.onDistributorChange, distributorId);
                }
                
                // 자동 폼 제출
                if (this.options.autoSubmit) {
                    this.submitForm();
                }
            });
        } else {
            // 총판이 선택되지 않은 경우 이미 초기화했으므로 폼만 제출
            // 콜백 실행
            if (this.options.onDistributorChange) {
                this.executeCallback(this.options.onDistributorChange, distributorId);
            }
            
            // 자동 폼 제출
            if (this.options.autoSubmit) {
                this.submitForm();
            }
        }
    }
    
    onAgencyChange() {
        this.log('onAgencyChange 함수 시작');
        const agencySelect = document.getElementById(this.options.agencySelectId);
        const agencyId = agencySelect ? agencySelect.value : '';
        
        this.log('대리점 변경:', agencyId);
        
        // 대리점이 변경되면 즉시 지점 선택박스 초기화
        this.clearSelect(this.options.branchSelectId);
        
        this.currentValues.agency = agencyId;
        this.currentValues.branch = '';  // 지점 값도 확실히 초기화
        
        // 지점 목록 로드 (비동기)
        this.log('지점 목록 로드 조건 확인: ', agencyId, document.getElementById(this.options.branchSelectId));
        if (agencyId && document.getElementById(this.options.branchSelectId)) {
            this.log('지점 목록 로드 함수 호출');
            this.loadBranches(agencyId).then(() => {
                // 지점 로드 완료 후 콜백 및 폼 제출
                this.executeAgencyChangeCallback();
            }).catch(() => {
                // 지점 로드 실패해도 콜백 및 폼 제출
                this.executeAgencyChangeCallback();
            });
        } else {
            // 지점 선택박스가 없거나 대리점이 선택되지 않은 경우
            this.executeAgencyChangeCallback();
        }
    }
    
    executeAgencyChangeCallback() {
        // 콜백 실행
        this.log('onAgencyChange 콜백 실행 시도');
        if (this.options.onAgencyChange) {
            this.executeCallback(this.options.onAgencyChange, this.currentValues.agency);
        }
        
        // 자동 폼 제출
        this.log('자동 폼 제출 조건 확인: ', this.options.autoSubmit);
        if (this.options.autoSubmit) {
            this.log('submitForm 함수 호출 시도');
            this.submitForm();
        }
        this.log('onAgencyChange 함수 종료');
    }
    
    onBranchChange() {
        const branchSelect = document.getElementById(this.options.branchSelectId);
        const branchId = branchSelect ? branchSelect.value : '';
        
        this.log('지점 변경:', branchId);
        
        this.currentValues.branch = branchId;
        
        // 콜백 실행
        if (this.options.onBranchChange) {
            this.executeCallback(this.options.onBranchChange, branchId);
        }
        
        // 자동 폼 제출
        if (this.options.autoSubmit) {
            this.submitForm();
        }
    }
    
    loadAgencies(distributorId, callback) {
        this.log('대리점 목록 로드 시작:', distributorId);
        
        const agencySelect = document.getElementById(this.options.agencySelectId);
        if (!agencySelect) {
            this.log('대리점 선택박스를 찾을 수 없음 - 콜백만 실행');
            // 대리점 선택박스가 없어도 콜백은 실행 (총판만 표시하는 경우)
            if (callback) {
                callback();
            }
            return;
        }
        
        // 지점 선택박스도 함께 초기화 (총판 변경 시 지점도 초기화되어야 함)
        this.clearSelect(this.options.branchSelectId);
        
        // 로딩 표시
        this.setLoadingState(agencySelect, true);
        
        // 총판 ID가 없으면 초기값 사용 (총판 관리자의 경우)
        const dtId = distributorId || this.currentValues.distributor;
        
        this.log('대리점 AJAX 요청 URL:', `${this.options.agencyEndpoint}?dt_id=${encodeURIComponent(dtId)}`);
        
        fetch(`${this.options.agencyEndpoint}?dt_id=${encodeURIComponent(dtId)}`)
            .then(response => {
                this.log('대리점 AJAX 응답 상태:', response.status, response.statusText);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                // 응답 텍스트를 먼저 로그로 확인
                return response.text().then(text => {
                    this.log('원본 응답 텍스트:', text);
                    
                    // HTML 경고 메시지가 JSON 앞에 있는 경우 제거
                    let cleanedText = text;
                    
                    // <br />나 <b> 태그가 포함된 경고 메시지 제거
                    if (text.includes('<br />') || text.includes('<b>Warning</b>')) {
                        // { 문자부터 시작하는 JSON 부분만 추출
                        const jsonStart = text.indexOf('{');
                        if (jsonStart !== -1) {
                            cleanedText = text.substring(jsonStart);
                            this.log('정리된 JSON 텍스트:', cleanedText);
                        }
                    }
                    
                    try {
                        return JSON.parse(cleanedText);
                    } catch (jsonError) {
                        this.log('JSON 파싱 오류:', jsonError);
                        this.log('파싱 실패한 텍스트 전체:', text);
                        throw new Error('서버 응답이 올바른 JSON 형식이 아닙니다: ' + text.substring(0, 100));
                    }
                });
            })
            .then(data => {
                this.log('대리점 목록 로드 완료 (전체 응답):', data);
                this.log('대리점 데이터 타입:', typeof data);
                
                // 선택박스가 여전히 존재하는지 확인
                const currentAgencySelect = document.getElementById(this.options.agencySelectId);
                if (!currentAgencySelect) {
                    this.log('대리점 선택박스가 DOM에서 사라짐!');
                    return;
                }
                
                if (data && data.debug) {
                    this.log('디버깅 정보:', data.debug);
                }
                
                // 응답 구조 확인 및 데이터 추출
                let agencies = [];
                if (data && typeof data === 'object') {
                    if (data.success && Array.isArray(data.data)) {
                        // 새로운 응답 구조: {success: true, data: [...]}
                        agencies = data.data;
                    } else if (Array.isArray(data)) {
                        // 기존 응답 구조: [...]
                        agencies = data;
                    }
                }
                
                this.log('처리할 대리점 데이터:', agencies);
                this.log('대리점 데이터 길이:', agencies.length);
                
                try {
                    // 기존 옵션들 확인 (첫 번째 옵션 제외하고 제거)
                    this.log('대리점 옵션 정리 전 옵션 수:', currentAgencySelect.options.length);
                    while (currentAgencySelect.options.length > 1) {
                        currentAgencySelect.remove(1);
                    }
                    this.log('대리점 옵션 정리 후 옵션 수:', currentAgencySelect.options.length);
                    
                    // 로딩 상태 해제
                    this.setLoadingState(currentAgencySelect, false);
                    this.log('로딩 상태 해제 완료');
                    
                    // 새 옵션 추가
                    agencies.forEach((agency, index) => {
                        this.log(`대리점 옵션 추가 중 [${index}]:`, agency);
                        const option = document.createElement('option');
                        option.value = agency.id;
                        option.textContent = `${agency.name} (${agency.id})`;
                        // 상위 계층 정보를 data attribute에 저장
                        if (agency.dt_id) {
                            option.setAttribute('data-dt-id', agency.dt_id);
                        }
                        currentAgencySelect.appendChild(option);
                        this.log(`옵션 추가 후 총 옵션 수: ${currentAgencySelect.options.length}`);
                    });
                    
                    this.log('대리점 옵션 추가 완료, 최종 옵션 수:', currentAgencySelect.options.length);
                    this.log('대리점 선택박스 모든 옵션들:');
                    for (let i = 0; i < currentAgencySelect.options.length; i++) {
                        this.log(`  옵션 [${i}]: value="${currentAgencySelect.options[i].value}", text="${currentAgencySelect.options[i].textContent}"`);
                    }
                } catch (optionError) {
                    this.log('옵션 추가 중 오류 발생:', optionError);
                    this.log('오류 스택:', optionError.stack);
                }
                
                // 선택값 복원 (currentValues에 저장된 값 사용)
                if (this.currentValues.agency) {
                    if (this.hasOption(agencySelect, this.currentValues.agency)) {
                        agencySelect.value = this.currentValues.agency;
                        this.log('대리점 선택값 복원:', this.currentValues.agency);
                    } else {
                        this.log('대리점 선택값 복원 실패 - 옵션 없음:', this.currentValues.agency);
                        this.currentValues.agency = '';
                    }
                }
                
                // 콜백 실행
                if (callback) {
                    callback();
                }
            })
            .catch(error => {
                this.log('대리점 목록 로드 실패:', error);
                this.log('오류 세부 정보:', {
                    name: error.name,
                    message: error.message,
                    stack: error.stack
                });
                this.setLoadingState(agencySelect, false);
                
                // 사용자에게 오류 메시지 표시 (디버그 모드일 때)
                if (this.options.debug) {
                    alert('대리점 목록 로드 실패: ' + error.message);
                }
            });
    }
    
    loadBranches(agencyId) {
        this.log('지점 목록 로드 시작:', agencyId);
        
        const branchSelect = document.getElementById(this.options.branchSelectId);
        
        // Promise 반환
        return new Promise((resolve, reject) => {
        if (!branchSelect) {
            this.log('지점 선택박스를 찾을 수 없음');
                resolve(); // 지점 선택박스가 없어도 Promise 해결
            return;
        }
        
        this.setLoadingState(branchSelect, true);
        
            this.log('지점 AJAX 요청 URL:', `${this.options.branchEndpoint}?ag_id=${agencyId}`);
            
            fetch(`${this.options.branchEndpoint}?ag_id=${agencyId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text().then(text => {
                    this.log('지점 원본 응답 텍스트:', text);
                    
                    // HTML 경고 메시지가 JSON 앞에 있는 경우 제거
                    let cleanedText = text;
                    
                    if (text.includes('<br />') || text.includes('<b>Warning</b>')) {
                        const jsonStart = text.indexOf('{');
                        if (jsonStart !== -1) {
                            cleanedText = text.substring(jsonStart);
                            this.log('지점 정리된 JSON 텍스트:', cleanedText);
                        }
                    }
                    
                    try {
                        return JSON.parse(cleanedText);
                    } catch (jsonError) {
                        this.log('지점 JSON 파싱 오류:', jsonError);
                        throw jsonError;
                    }
                });
            })
            .then(data => {
                this.log('지점 목록 로드 완료:', data);
                
                    // 응답 구조 확인 및 데이터 추출
                    let branches = [];
                    if (data && typeof data === 'object') {
                        if (data.success && Array.isArray(data.data)) {
                            // 새로운 응답 구조: {success: true, data: [...]}
                            branches = data.data;
                        } else if (Array.isArray(data)) {
                            // 기존 응답 구조: [...]
                            branches = data;
                        }
                    }
                    
                    this.log('처리할 지점 데이터:', branches);
                    this.log('지점 데이터 길이:', branches.length);
                    
                    // 지점 선택박스 옵션 정리 (기본 옵션 제외)
                    this.log('지점 옵션 정리 전 옵션 수:', branchSelect.options.length);
                    // 첫 번째 옵션(기본 옵션)을 제외하고 모든 옵션 제거
                    while (branchSelect.options.length > 1) {
                        branchSelect.removeChild(branchSelect.lastChild);
                    }
                    this.log('지점 옵션 정리 후 옵션 수:', branchSelect.options.length);
                    
                    // 지점 데이터가 있는 경우 옵션 추가
                    if (branches && branches.length > 0) {
                        branches.forEach((branch, index) => {
                            this.log(`지점 옵션 추가 중 [${index}]:`, branch);
                            const option = document.createElement('option');
                            option.value = branch.id;
                            option.textContent = `${branch.name} (${branch.id})`;
                            // 상위 계층 정보를 data attribute에 저장
                            if (branch.dt_id) {
                                option.setAttribute('data-dt-id', branch.dt_id);
                            }
                            if (branch.ag_id) {
                                option.setAttribute('data-ag-id', branch.ag_id);
                            }
                            branchSelect.appendChild(option);
                            this.log('옵션 추가 후 총 옵션 수:', branchSelect.options.length);
                        });
                        this.log('지점 옵션 추가 완료, 최종 옵션 수:', branchSelect.options.length);
                    } else {
                        this.log('지점 데이터가 없습니다.');
                    }
                    
                    // 현재 저장된 지점 값이 있으면 선택
                    if (this.currentValues.branch && this.hasOption(branchSelect, this.currentValues.branch)) {
                        branchSelect.value = this.currentValues.branch;
                        this.log('지점 선택값 복원:', this.currentValues.branch);
                    }
                    
                    // 지점 선택박스의 모든 옵션 로그 출력
                    this.log('지점 선택박스 모든 옵션들:');
                    for (let i = 0; i < branchSelect.options.length; i++) {
                        this.log(`  옵션 [${i}]: value="${branchSelect.options[i].value}", text="${branchSelect.options[i].text}"`);
                    }
                    
                    resolve();
            })
            .catch(error => {
                    console.error('[DmkChainSelect] 지점 목록 로드 오류:', error);
                    this.clearSelect(this.options.branchSelectId);
                    reject(error);
                })
                .finally(() => {
                // 로딩 상태만 해제 (옵션은 건드리지 않음)
                if (branchSelect) {
                    branchSelect.classList.remove('loading');
                    branchSelect.disabled = false;
                }
                });
            });
    }
    
    populateSelect(selectId, data, defaultOptionText) {
        const $select = $(`#${selectId}`);
        if (!$select.length) {
            this.log(`populateSelect: '${selectId}' 요소를 찾을 수 없습니다.`);
            return;
        }

        this.log(`populateSelect 최종 시도: '${selectId}', 데이터:`, data);
        
        // select2 사용 시, 파괴하고 다시 초기화하는 것이 안정적일 수 있습니다.
        // if ($select.data('select2')) {
        //     $select.select2('destroy');
        // }

        this.clearSelect(selectId, defaultOptionText);

        try {
            if (data && data.length > 0) {
                this.log(`'${selectId}'에 ${data.length}개의 새 옵션을 추가합니다.`);
                $.each(data, (index, item) => {
                    // new Option(text, value, defaultSelected, selected)
                    const option = new Option(item.name, item.id, false, false);
                    $select.append(option);
                });
            } else {
                this.log(`'${selectId}'에 추가할 데이터가 없습니다.`);
            }
        } catch (e) {
            this.log(`populateSelect에서 옵션 추가 중 오류 발생:`, e);
        }
        
        // 변경사항을 select2와 같은 다른 라이브러리에 알립니다.
        $select.trigger('change');

        // 최종 확인
        this.log(`'${selectId}' 최종 옵션 개수:`, $select.find('option').length);
        this.log(`'${selectId}' 최종 HTML:`, $select.html());
    }

    clearSelect(selectElementId, placeholder = '') {
        const select = document.getElementById(selectElementId);
        if (!select) {
            this.log(`clearSelect: '${selectElementId}' 요소를 찾을 수 없습니다.`);
            return;
        }
        
        this.log(`clearSelect: '${selectElementId}' 선택박스 초기화 시작`);
        
        // 선택값 초기화
        select.value = '';
        
        // 첫 번째 옵션(전체/선택안함) 제외하고 모든 옵션 제거
        while (select.options.length > 1) {
            select.remove(1);
        }
        
        // 첫 번째 옵션이 선택되도록 보장
        if (select.options.length > 0) {
            select.selectedIndex = 0;
        }
        
        this.log(`clearSelect: '${selectElementId}' 초기화 완료, 남은 옵션 수: ${select.options.length}`);
    }
    
    hasOption(select, value) {
        return Array.from(select.options).some(option => option.value === value);
    }
    
    setLoadingState(select, isLoading) {
        if (isLoading) {
            select.classList.add('loading');
            select.disabled = true;
            // 로딩 중임을 나타내는 옵션 추가
            const loadingOption = document.createElement('option');
            loadingOption.value = '';
            loadingOption.textContent = '로딩 중...';
            loadingOption.disabled = true;
            // 기존 옵션들 제거하고 로딩 옵션만 표시
            while (select.options.length > 1) {
                select.remove(1);
            }
            select.appendChild(loadingOption);
        } else {
            select.classList.remove('loading');
            select.disabled = false;
            // 로딩 옵션 제거 (기본 옵션만 남김)
            while (select.options.length > 1) {
                select.remove(1);
            }
        }
    }
    
    submitForm() {
        this.log('폼 제출 시도', this.options.formId);
        
        // GET 방식으로 현재 페이지에 파라미터를 포함해서 이동
        const currentUrl = new URL(window.location);
        
        // 현재 선택된 값들을 URL 파라미터로 설정
        const distributorSelect = document.getElementById(this.options.distributorSelectId);
        const agencySelect = document.getElementById(this.options.agencySelectId);
        const branchSelect = document.getElementById(this.options.branchSelectId);
        
        if (distributorSelect) {
            if (distributorSelect.value) {
                currentUrl.searchParams.set(this.options.distributorSelectId, distributorSelect.value);
            } else {
                currentUrl.searchParams.delete(this.options.distributorSelectId);
            }
        }
        
        if (agencySelect) {
            if (agencySelect.value) {
                currentUrl.searchParams.set(this.options.agencySelectId, agencySelect.value);
            } else {
                currentUrl.searchParams.delete(this.options.agencySelectId);
            }
        }
        
        if (branchSelect) {
            if (branchSelect.value) {
                currentUrl.searchParams.set(this.options.branchSelectId, branchSelect.value);
            } else {
                currentUrl.searchParams.delete(this.options.branchSelectId);
            }
        }
        
        // 페이지를 1로 리셋 (새로운 필터 조건이므로)
        currentUrl.searchParams.set('page', '1');
        
        this.log('새로운 URL로 이동:', currentUrl.toString());
        
        // 페이지 이동
        window.location.href = currentUrl.toString();
    }
    
    log(...args) {
        if (this.options.debug) {
            console.log('[DmkChainSelect]', ...args);
        }
    }
    
    executeCallback(callback, ...args) {
        try {
            if (typeof callback === 'function') {
                callback(...args);
            } else if (typeof callback === 'string') {
                // 문자열인 경우 전역 함수로 실행
                if (typeof window[callback] === 'function') {
                    window[callback](...args);
                } else {
                    this.log('콜백 함수를 찾을 수 없습니다:', callback);
                }
            }
        } catch (error) {
            this.log('콜백 함수 실행 중 오류:', error);
        }
    }
    
    
    // 현재 선택값 반환
    getValues() {
        return { ...this.currentValues };
    }
    
    // 선택값 설정
    setValues(values) {
        if (values.distributor !== undefined) {
            this.currentValues.distributor = values.distributor;
            const distributorSelect = document.getElementById(this.options.distributorSelectId);
            if (distributorSelect) {
                distributorSelect.value = values.distributor;
            }
        }
        
        if (values.agency !== undefined) {
            this.currentValues.agency = values.agency;
        }
        
        if (values.branch !== undefined) {
            this.currentValues.branch = values.branch;
        }
        
        // 데이터 재로드
        this.loadInitialData();
    }
}

// 전역 함수로 노출 (중복 로드가 아닌 경우만)
if (typeof window.DmkChainSelect === 'undefined') {
    window.DmkChainSelect = DmkChainSelect;
}

} // 중복 방지 블록 종료