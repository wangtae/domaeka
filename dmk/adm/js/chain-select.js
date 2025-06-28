/**
 * 도매까 계층별 선택박스 공통 라이브러리
 * 총판-대리점-지점 체인 선택박스 구현
 */

class DmkChainSelect {
    constructor(options) {
        this.options = {
            // 선택박스 ID
            distributorSelectId: 'sdt_id',
            agencySelectId: 'sag_id',
            branchSelectId: 'sbr_id',
            
            // AJAX 엔드포인트
            agencyEndpoint: './get_agencies.php',
            branchEndpoint: './get_branches.php',
            
            // 초기 선택값
            initialDistributor: '',
            initialAgency: '',
            initialBranch: '',
            
            // 폼 제출 여부
            autoSubmit: true,
            formId: 'fsearch',
            
            // 콜백 함수
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
        
        // 하위 선택박스 초기화
        this.clearSelect(this.options.agencySelectId);
        this.clearSelect(this.options.branchSelectId);
        
        this.currentValues.distributor = distributorId;
        this.currentValues.agency = '';
        this.currentValues.branch = '';
        
        if (distributorId) {
            // AJAX 요청 완료 후 폼 제출
            this.loadAgencies(distributorId, () => {
                // 콜백 실행
                if (this.options.onDistributorChange) {
                    this.options.onDistributorChange(distributorId);
                }
                
                // 자동 폼 제출
                if (this.options.autoSubmit) {
                    this.submitForm();
                }
            });
        } else {
            // 총판이 선택되지 않은 경우 바로 폼 제출
            // 콜백 실행
            if (this.options.onDistributorChange) {
                this.options.onDistributorChange(distributorId);
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
        
        // 하위 선택박스 초기화
        this.clearSelect(this.options.branchSelectId);
        
        this.currentValues.agency = agencyId;
        this.currentValues.branch = '';
        
        // 지점 목록 로드 (비동기)
        this.log('지점 목록 로드 조건 확인: ', agencyId, document.getElementById(this.options.branchSelectId));
        if (agencyId && document.getElementById(this.options.branchSelectId)) {
            this.log('지점 목록 로드 함수 호출');
            this.loadBranches(agencyId);
        }
        
            // 콜백 실행
        this.log('onAgencyChange 콜백 실행 시도');
            if (this.options.onAgencyChange) {
                this.options.onAgencyChange(agencyId);
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
            this.options.onBranchChange(branchId);
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
            this.log('대리점 선택박스를 찾을 수 없음');
            return;
        }
        
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
                if (data && data.debug) {
                    this.log('디버깅 정보:', data.debug);
                }
                
                // 기존 옵션 제거 (첫 번째 옵션 제외)
                this.clearSelect(this.options.agencySelectId);
                
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
                
                // 새 옵션 추가
                agencies.forEach(agency => {
                    const option = document.createElement('option');
                    option.value = agency.id;
                    option.textContent = `${agency.name} (${agency.id})`;
                    agencySelect.appendChild(option);
                });
                
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
                
                // 로딩 상태 해제
                this.setLoadingState(agencySelect, false);
                
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
                this.clearSelect(this.options.branchSelectId);
                
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
                    
                    branches.forEach(branch => {
                    const option = document.createElement('option');
                    option.value = branch.id;
                        option.textContent = branch.name;
                    branchSelect.appendChild(option);
                });
                
                    if (this.currentValues.branch && this.hasOption(branchSelect, this.currentValues.branch)) {
                        branchSelect.value = this.currentValues.branch;
                    }
                    resolve();
            })
            .catch(error => {
                    console.error('[DmkChainSelect] 지점 목록 로드 오류:', error);
                    this.clearSelect(this.options.branchSelectId);
                    reject(error);
                })
                .finally(() => {
                this.setLoadingState(branchSelect, false);
                });
            });
    }
    
    clearSelect(selectId) {
        const select = document.getElementById(selectId);
        if (!select) return;
        
        // 첫 번째 옵션(전체) 제외하고 모든 옵션 제거
        while (select.options.length > 1) {
            select.remove(1);
        }
        
        select.value = '';
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
        const form = document.getElementById(this.options.formId);
        if (form) {
            form.submit();
        } else {
            this.log('폼을 찾을 수 없음:', this.options.formId);
        }
    }
    
    log(...args) {
        if (this.options.debug) {
            console.log('[DmkChainSelect]', ...args);
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

// 전역 함수로 노출
window.DmkChainSelect = DmkChainSelect; 