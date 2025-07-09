jQuery(function($){
    // Datepicker 초기화 및 한국어 지역화
    $.datepicker.regional["ko"] = {
        closeText: "닫기",
        prevText: "이전달",
        nextText: "다음달",
        currentText: "오늘",
        monthNames: ["1월(JAN)","2월(FEB)","3월(MAR)","4월(APR)","5월(MAY)","6월(JUN)", "7월(JUL)","8월(AUG)","9월(SEP)","10월(OCT)","11월(NOV)","12월(DEC)"],
        monthNamesShort: ["1월","2월","3월","4월","5월","6월", "7월","8월","9월","10월","11월","12월"],
        dayNames: ["일","월","화","수","목","금","토"],
        dayNamesShort: ["일","월","화","수","목","금","토"],
        dayNamesMin: ["일","월","화","수","목","금","토"],
        weekHeader: "Wk",
        dateFormat: "yy-mm-dd",
        firstDay: 0,
        isRTL: false,
        showMonthAfterYear: true,
        yearSuffix: ""
    };
    $.datepicker.setDefaults($.datepicker.regional["ko"]);

    $("#dmk_it_valid_start_date").datepicker({
        changeMonth: true,
        changeYear: true,
        dateFormat: "yy-mm-dd",
        showButtonPanel: true,
        yearRange: "c-99:c+99"
    });
    $("#dmk_it_valid_start_date_btn").on("click", function(){ $("#dmk_it_valid_start_date").datepicker("show"); });

    $("#dmk_it_valid_end_date").datepicker({
        changeMonth: true,
        changeYear: true,
        dateFormat: "yy-mm-dd",
        showButtonPanel: true,
        yearRange: "c-99:c+99"
    });
    $("#dmk_it_valid_end_date_btn").on("click", function(){ $("#dmk_it_valid_end_date").datepicker("show"); });

    // 유효기간 초기화 함수
    window.clearValidDates = function() {
        $("#dmk_it_valid_start_date").val('');
        $("#dmk_it_valid_end_date").val('');
    };

    var $ownerTypeSelect = $('#dmk_it_owner_type_top');
    var $dtSelect = $('#dt_id');
    var $agSelect = $('#ag_id');
    var $brSelect = $('#br_id');
    
    // PHP에서 전달받은 전역 변수 사용
    var dmkJsConsts = window.DMK_JS_CONSTS || {};
    var dmkAuth = window.DMK_AUTH || {};
    var selectedDtId = window.SELECTED_DT_ID || '';
    var selectedAgId = window.SELECTED_AG_ID || '';
    var selectedBrId = window.SELECTED_BR_ID || '';

    function populateDropdown(targetSelect, items, selectedValue, emptyOptionText) {
        targetSelect.empty();
        targetSelect.append('<option value="">' + emptyOptionText + '</option>');
        
        if (Array.isArray(items)) {
            $.each(items, function(index, item) {
                var id = item.id || item.mb_id || item.ag_id || item.br_id;
                var name = item.name || item.mb_name || item.ag_name || item.br_name;
                
                if (id && name) {
                    var selectedAttr = (selectedValue === id) ? 'selected' : '';
                    targetSelect.append('<option value="' + id + '" ' + selectedAttr + '>' + name + ' (' + id + ')</option>');
                }
            });
        }
    }

    function loadOwnerIds(ownerType, parentId, targetSelect, selectedValue, emptyOptionText) {
        $.ajax({
            url: './ajax_get_dmk_owner_ids.php',
            type: 'GET',
            dataType: 'json',
            data: { owner_type: ownerType, parent_id: parentId },
            success: function(data) {
                if (data.error) {
                    alert('목록을 가져오는 중 오류가 발생했습니다: ' + data.error);
                } else {
                    populateDropdown(targetSelect, data, selectedValue, emptyOptionText);
                }
            },
            error: function(xhr, status, error) {
                alert('목록을 가져오는 중 네트워크 오류가 발생했습니다.');
            }
        });
    }

    // 초기 로드 시 소유 계층 드롭다운 활성화/비활성화 및 데이터 로드
    function initializeOwnerDropdowns() {
        var currentOwnerType = $ownerTypeSelect.val();
        $dtSelect.prop('disabled', true);
        $agSelect.prop('disabled', true);
        $brSelect.prop('disabled', true);

        if (currentOwnerType == dmkJsConsts.DMK_OWNER_TYPE_SUPER_ADMIN) {
            $dtSelect.prop('disabled', false);
            if (selectedDtId) {
                loadOwnerIds(dmkJsConsts.DMK_OWNER_TYPE_AGENCY, selectedDtId, $agSelect, selectedAgId, '- 대리점 선택 -');
            }
        } else if (currentOwnerType == dmkJsConsts.DMK_OWNER_TYPE_DISTRIBUTOR) {
            $agSelect.prop('disabled', false);
            if (selectedAgId) {
                loadOwnerIds(dmkJsConsts.DMK_OWNER_TYPE_BRANCH, selectedAgId, $brSelect, selectedBrId, '- 지점 선택 -');
            }
        } else if (currentOwnerType == dmkJsConsts.DMK_OWNER_TYPE_AGENCY) {
            $brSelect.prop('disabled', false);
        }

        // 현재 관리자 유형에 따른 초기 비활성화
        if (dmkAuth.mb_type == dmkJsConsts.DMK_MB_TYPE_DISTRIBUTOR) {
            $ownerTypeSelect.prop('disabled', true);
        } else if (dmkAuth.mb_type == dmkJsConsts.DMK_MB_TYPE_AGENCY) {
            $ownerTypeSelect.prop('disabled', true);
            $dtSelect.prop('disabled', true);
        } else if (dmkAuth.mb_type == dmkJsConsts.DMK_MB_TYPE_BRANCH) {
            $ownerTypeSelect.prop('disabled', true);
            $dtSelect.prop('disabled', true);
            $agSelect.prop('disabled', true);
        }
    }

    // 이벤트 리스너: 최상위 소유 계층 변경 시 하위 드롭다운 업데이트
    $ownerTypeSelect.on('change', function() {
        var ownerType = $(this).val();
        $dtSelect.empty().append('<option value="">- 총판 선택 -</option>').prop('disabled', true);
        $agSelect.empty().append('<option value="">- 대리점 선택 -</option>').prop('disabled', true);
        $brSelect.empty().append('<option value="">- 지점 선택 -</option>').prop('disabled', true);

        if (ownerType == dmkJsConsts.DMK_OWNER_TYPE_SUPER_ADMIN) {
            // 최고 관리자가 선택되면 총판 드롭다운 활성화
            $dtSelect.prop('disabled', false);
            loadOwnerIds(dmkJsConsts.DMK_OWNER_TYPE_DISTRIBUTOR, '', $dtSelect, '', '- 총판 선택 -');
        } else if (ownerType == dmkJsConsts.DMK_OWNER_TYPE_DISTRIBUTOR) {
            // 총판이 선택되면 대리점 드롭다운 활성화 (총판 ID는 현재 로그인한 총판 ID)
            $agSelect.prop('disabled', false);
            loadOwnerIds(dmkJsConsts.DMK_OWNER_TYPE_AGENCY, dmkAuth.dt_id, $agSelect, '', '- 대리점 선택 -');
        } else if (ownerType == dmkJsConsts.DMK_OWNER_TYPE_AGENCY) {
            // 대리점이 선택되면 지점 드롭다운 활성화 (대리점 ID는 현재 로그인한 대리점 ID)
            $brSelect.prop('disabled', false);
            loadOwnerIds(dmkJsConsts.DMK_OWNER_TYPE_BRANCH, dmkAuth.ag_id, $brSelect, '', '- 지점 선택 -');
        } else if (ownerType == dmkJsConsts.DMK_OWNER_TYPE_BRANCH) {
            // 지점이 선택되면 하위 없음
        }
    });

    // 총판 선택 변경 시
    $dtSelect.on('change', function() {
        var dt_id = $(this).val();
        $agSelect.empty().append('<option value="">- 대리점 선택 -</option>').prop('disabled', true);
        $brSelect.empty().append('<option value="">- 지점 선택 -</option>').prop('disabled', true);

        if (dt_id) {
            $agSelect.prop('disabled', false);
            loadOwnerIds(dmkJsConsts.DMK_OWNER_TYPE_AGENCY, dt_id, $agSelect, '', '- 대리점 선택 -');
        }
    });

    // 대리점 선택 변경 시
    $agSelect.on('change', function() {
        var ag_id = $(this).val();
        $brSelect.empty().append('<option value="">- 지점 선택 -</option>').prop('disabled', true);

        if (ag_id) {
            $brSelect.prop('disabled', false);
            loadOwnerIds(dmkJsConsts.DMK_OWNER_TYPE_BRANCH, ag_id, $brSelect, '', '- 지점 선택 -');
        }
    });

    // 페이지 로드 시 초기 호출
    initializeOwnerDropdowns();

}); 