<?php
/**
 * 스케줄링 발송 관리
 * 향후 스케줄 테이블 구현 시 사용할 예정
 */

include_once('./_common.php');

auth_check('180600', 'r');

$g5['title'] = '스케줄링 발송 관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

?>
<div class="local_ov01 local_ov">
    <span class="btn_ov01"><span class="ov_txt">총 스케줄</span><span class="ov_num">0</span></span>
    <span class="btn_ov01"><span class="ov_txt">활성</span><span class="ov_num">0</span></span>
    <span class="btn_ov01"><span class="ov_txt">대기중</span><span class="ov_num">0</span></span>
    <span class="btn_ov01"><span class="ov_txt">완료</span><span class="ov_num">0</span></span>
</div>

<div class="local_desc01 local_desc">
    <p><strong>개발 예정 기능</strong></p>
    <p>카카오톡 메시지 예약 발송 기능을 관리합니다. 향후 스케줄 관련 테이블이 구현되면 다음 기능들이 제공됩니다:</p>
    <ul>
        <li>메시지 예약 발송 등록/수정/삭제</li>
        <li>반복 발송 스케줄 설정</li>
        <li>발송 상태 모니터링</li>
        <li>발송 이력 조회</li>
        <li>채팅방별 발송 권한 관리</li>
    </ul>
</div>

<div class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col">스케줄 ID</th>
        <th scope="col">제목</th>
        <th scope="col">대상 채팅방</th>
        <th scope="col">발송 예정 시간</th>
        <th scope="col">반복 설정</th>
        <th scope="col">상태</th>
        <th scope="col">등록일</th>
        <th scope="col">관리</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td colspan="8" class="empty_table">
            <div style="padding: 50px; text-align: center; color: #999;">
                <i class="fa fa-clock-o" style="font-size: 48px; margin-bottom: 20px; display: block;"></i>
                <h3>스케줄링 발송 기능 준비중</h3>
                <p>스케줄 관련 데이터베이스 테이블이 구현되면<br>예약 메시지 발송 기능을 사용할 수 있습니다.</p>
                <br>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; text-align: left;">
                    <h4>계획된 기능</h4>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>일회성/반복 메시지 예약</li>
                        <li>채팅방별 발송 대상 선택</li>
                        <li>텍스트/이미지/파일 발송 지원</li>
                        <li>발송 결과 모니터링</li>
                        <li>실패 시 재시도 설정</li>
                    </ul>
                </div>
            </div>
        </td>
    </tr>
    </tbody>
    </table>
</div>

<style>
.empty_table { 
    text-align: center; 
    padding: 20px; 
    color: #999; 
}
.empty_table h3 {
    margin: 20px 0 10px 0;
    color: #666;
}
.empty_table h4 {
    margin: 0 0 10px 0;
    color: #333;
}
.empty_table ul {
    text-align: left;
    color: #666;
}
</style>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');
?>