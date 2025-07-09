<?php

/**
 * 가입정보 입력 수정
 */
if (!defined('_GNUBOARD_')) {
	exit;
}
add_stylesheet('<link rel="stylesheet" href="' . BB_ASSETS_URL . '/css/skin/member/register.css">', 120);
add_javascript('<script src="' . G5_JS_URL . '/jquery.register_form.js"></script>', 0);
if ($config['cf_cert_use'] && ($config['cf_cert_simple'] || $config['cf_cert_ipin'] || $config['cf_cert_hp']))
	add_javascript('<script src="' . G5_JS_URL . '/certify.js?v=' . G5_JS_VER . '"></script>', 0);
?>

<div class="register-form-wrap">
	<div class="register-header container mt-2 mb-2">
		<div class="bg-white border p-3 p-lg-4">
			<h1 class="fs-3 fw-bolder mb-0"><i class="bi bi-person-fill-add"></i> <?php echo $g5['title'] ?></h1>
		</div>
	</div>
	<div class="container">
		<div class="bg-white p-2 p-lg-4 border">
			<form id="fregisterform" name="fregisterform" action="<?php echo $register_action_url ?>" onsubmit="return fregisterform_submit(this);" method="post" enctype="multipart/form-data" autocomplete="off">
				<input type="hidden" name="w" value="<?php echo $w ?>">
				<input type="hidden" name="url" value="<?php echo $urlencode ?>">
				<input type="hidden" name="agree" value="<?php echo $agree ?>">
				<input type="hidden" name="agree2" value="<?php echo $agree2 ?>">
				<input type="hidden" name="cert_type" value="<?php echo $member['mb_certify']; ?>">
				<input type="hidden" name="cert_no" value="">
				<?php if (isset($member['mb_sex'])) {  ?><input type="hidden" name="mb_sex" value="<?php echo $member['mb_sex'] ?>"><?php }  ?>
				<?php if (isset($member['mb_nick_date']) && $member['mb_nick_date'] > date("Y-m-d", G5_SERVER_TIME - ($config['cf_nick_modify'] * 86400))) { ?>
					<input type="hidden" name="mb_nick_default" value="<?php echo get_text($member['mb_nick']) ?>">
					<input type="hidden" name="mb_nick" value="<?php echo get_text($member['mb_nick']) ?>">
				<?php }  ?>

				<div class="text-end">
					<i class="bi bi-patch-check"></i> 필수입력
				</div>
				<section class="site-info mb-5 pb-5">
					<h2 class="fw-bolder fs-5 border-bottom pb-2 mb-2">사이트 이용정보 입력</h2>
					<div class="input-group">
						<label for="reg_mb_id" class="input-group-text">
							아이디 <i class="bi bi-patch-check"></i>
						</label>
						<input type="text" name="mb_id" value="<?php echo $member['mb_id'] ?>" id="reg_mb_id" <?php echo $required ?> <?php echo $readonly ?> class="form-control  <?php echo $required ?> <?php echo $readonly ?>" minlength="3" maxlength="20" placeholder="아이디">
						<span class="input-group-text">
							<i class="bi bi-info-circle-fill" aria-hidden="true" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="영문자, 숫자, _ 만 입력 가능. 최소 3자이상 입력하세요."></i>
							<span id="msg_mb_id"></span>
						</span>
					</div>
					<div class="input-group">
						<label for="reg_mb_password" class="input-group-text">비밀번호 <i class="bi bi-patch-check"></i> </label>
						<input type="password" name="mb_password" id="reg_mb_password" autocomplete="new-password" <?php echo $required ?> class="form-control  <?php echo $required ?>" minlength="3" maxlength="20" placeholder="비밀번호">
					</div>
					<div class="input-group">
						<label for="reg_mb_password_re" class="input-group-text">비밀번호 확인 <i class="bi bi-patch-check"></i></label>
						<input type="password" name="mb_password_re" id="reg_mb_password_re" <?php echo $required ?> class="form-control  <?php echo $required ?>" minlength="3" maxlength="20" placeholder="비밀번호 확인">
					</div>
				</section>

				<section class="privacy-info mb-5 border-bottom pb-5">
					<h2 class="fw-bolder fs-5 border-bottom pb-2 mb-2">개인정보 입력</h2>
					<div class="btn-group mb-2">
						<?php
						$desc_name = '';
						$desc_phone = '';
						if ($config['cf_cert_use']) {
							$desc_name = '<span class="cert_desc"> 본인확인 시 자동입력</span>';
							$desc_phone = '<span class="cert_desc"> 본인확인 시 자동입력</span>';

							if (!$config['cf_cert_simple'] && !$config['cf_cert_hp'] && $config['cf_cert_ipin']) {
								$desc_phone = '';
							}

							if ($config['cf_cert_simple']) {
								echo '<button type="button" id="win_sa_kakao_cert" class="btn btn-outline-secondary btn-sm win_sa_cert" data-type="">간편인증</button>' . PHP_EOL;
							}
							if ($config['cf_cert_hp'])
								echo '<button type="button" id="win_hp_cert" class="btn btn-outline-secondary btn-sm">휴대폰 본인확인</button>' . PHP_EOL;
							if ($config['cf_cert_ipin'])
								echo '<button type="button" id="win_ipin_cert" class="btn btn-outline-secondary btn-sm">아이핀 본인확인</button>' . PHP_EOL;

							echo '<span class="cert_req"> &nbsp; <i class="bi bi-patch-check"></i></span>';
							echo '<noscript>본인확인을 위해서는 자바스크립트 사용이 가능해야합니다.</noscript>' . PHP_EOL;
						}
						?>
					</div>
					<?php
					if ($config['cf_cert_use'] && $member['mb_certify']) {
						switch ($member['mb_certify']) {
							case "simple":
								$mb_cert = "간편인증";
								break;
							case "ipin":
								$mb_cert = "아이핀";
								break;
							case "hp":
								$mb_cert = "휴대폰";
								break;
						}
					?>
						<div id="msg_certify" class="alert alert-info">
							<strong><?php echo $mb_cert; ?> 본인확인</strong><?php if ($member['mb_adult']) { ?> 및 <strong>성인인증</strong><?php } ?> 완료
						</div>
					<?php } ?>

					<div class="input-group mb-2">
						<label for="reg_mb_name" class="input-group-text">이름 <i class="bi bi-patch-check"></i></label>
						<input type="text" id="reg_mb_name" name="mb_name" value="<?php echo get_text($member['mb_name']) ?>" <?php echo $required ?> <?php echo $readonly; ?> class="form-control  <?php echo $required ?> <?php echo $name_readonly ?>" size="10" placeholder="이름">
						<span class="input-group-text"><?php echo $desc_name ?></span>
					</div>
					<?php if ($req_nick) {  ?>
						<div class="input-group mb-2">
							<label for="reg_mb_nick" class="input-group-text">
								닉네임<i class="bi bi-patch-check"></i>
							</label>
							<input type="hidden" name="mb_nick_default" value="<?php echo isset($member['mb_nick']) ? get_text($member['mb_nick']) : ''; ?>">
							<input type="text" name="mb_nick" value="<?php echo isset($member['mb_nick']) ? get_text($member['mb_nick']) : ''; ?>" id="reg_mb_nick" required class="form-control required nospace " size="10" maxlength="20" placeholder="닉네임">
							<span class="input-group-text">
								<i class="bi bi-info-circle-fill" aria-hidden="true" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="공백없이 한글,영문,숫자만 입력 가능 (한글2자, 영문4자 이상) 닉네임을 바꾸시면 앞으로 <?php echo (int)$config['cf_nick_modify'] ?>일 이내에는 변경 할 수 없습니다."></i>
								<span id="msg_mb_nick"></span>
							</span>
						</div>

					<?php }  ?>
					<div class="input-group mb-2">
						<label for="reg_mb_email" class="input-group-text">E-mail<i class="bi bi-patch-check"></i></label>
						<input type="hidden" name="old_email" value="<?php echo $member['mb_email'] ?>">
						<input type="text" name="mb_email" value="<?php echo isset($member['mb_email']) ? $member['mb_email'] : ''; ?>" id="reg_mb_email" required class="form-control email  required" size="70" maxlength="100" placeholder="E-mail">
						<?php if ($config['cf_use_email_certify']) {  ?>
							<span class="input-group-text">
								<?php if ($w == '') {
									echo '<i class="bi bi-info-circle-fill" aria-hidden="true" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="E-mail 로 발송된 내용을 확인한 후 인증하셔야 회원가입이 완료됩니다."></i>';
								}  ?>
								<?php if ($w == 'u') {
									echo '<i class="bi bi-info-circle-fill" aria-hidden="true" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="E-mail 주소를 변경하시면 다시 인증하셔야 합니다."></i>';
								}  ?>
							</span>
						<?php }  ?>
					</div>
					<?php if ($config['cf_use_homepage']) {  ?>
						<div class="input-group mb-2">
							<label for="reg_mb_homepage" class="input-group-text">홈페이지<?php if ($config['cf_req_homepage']) { ?> <i class="bi bi-patch-check"></i><?php } ?></label>
							<input type="text" name="mb_homepage" value="<?php echo get_text($member['mb_homepage']) ?>" id="reg_mb_homepage" <?php echo $config['cf_req_homepage'] ? "required" : ""; ?> class="form-control  <?php echo $config['cf_req_homepage'] ? "required" : ""; ?>" size="70" maxlength="255" placeholder="홈페이지">
						</div>
					<?php }  ?>

					<?php if ($config['cf_use_tel']) {  ?>
						<div class="input-group mb-2">
							<label for="reg_mb_tel" class="input-group-text">전화번호<?php if ($config['cf_req_tel']) { ?> <i class="bi bi-patch-check"></i><?php } ?></label>
							<input type="text" name="mb_tel" value="<?php echo get_text($member['mb_tel']) ?>" id="reg_mb_tel" <?php echo $config['cf_req_tel'] ? "required" : ""; ?> class="form-control  <?php echo $config['cf_req_tel'] ? "required" : ""; ?>" maxlength="20" placeholder="전화번호">
						</div>
					<?php }  ?>
					<?php if ($config['cf_use_hp'] || ($config["cf_cert_use"] && ($config['cf_cert_hp'] || $config['cf_cert_simple']))) {  ?>
						<div class="input-group mb-2" class="input-group-text">
							<label for="reg_mb_hp" class="input-group-text">휴대폰번호<?php if (!empty($hp_required)) { ?> <i class="bi bi-patch-check"></i><?php } ?></label>
							<input type="text" name="mb_hp" value="<?php echo get_text($member['mb_hp']) ?>" id="reg_mb_hp" <?php echo $hp_required; ?> <?php echo $hp_readonly; ?> class="form-control  <?php echo $hp_required; ?> <?php echo $hp_readonly; ?>" maxlength="20" placeholder="휴대폰번호">
							<span class="input-group-text"><?php echo $desc_phone ?></span>
							<?php if ($config['cf_cert_use'] && ($config['cf_cert_hp'] || $config['cf_cert_simple'])) { ?>
								<input type="hidden" name="old_mb_hp" value="<?php echo get_text($member['mb_hp']) ?>">
							<?php } ?>
						</div>
					<?php }  ?>

					<?php if ($config['cf_use_addr']) { ?>
						<div class="d-flex flex-column mb-4 mt-4 address-info">
							<label class="fw-bolder">주소</label>
							<?php if ($config['cf_req_addr']) { ?><i class="bi bi-patch-check"></i><?php }  ?>
							<div class="input-group">
								<label for="reg_mb_zip" class="input-group-text">우편번호<?php echo $config['cf_req_addr'] ? ' <i class="bi bi-patch-check"></i>' : ''; ?></label>
								<input type="text" name="mb_zip" value="<?php echo $member['mb_zip1'] . $member['mb_zip2']; ?>" id="reg_mb_zip" <?php echo $config['cf_req_addr'] ? "required" : ""; ?> class="form-control twopart_input <?php echo $config['cf_req_addr'] ? "required" : ""; ?>" size="5" maxlength="6" placeholder="우편번호">
								<button type="button" class="btn btn-outline-info" onclick="win_zip('fregisterform', 'mb_zip', 'mb_addr1', 'mb_addr2', 'mb_addr3', 'mb_addr_jibeon');"><i class="bi bi-search"></i></button>
							</div>
							<div class="input-group">
								<input type="text" name="mb_addr1" value="<?php echo get_text($member['mb_addr1']) ?>" id="reg_mb_addr1" <?php echo $config['cf_req_addr'] ? "required" : ""; ?> class="form-control frm_address  <?php echo $config['cf_req_addr'] ? "required" : ""; ?>" size="50" placeholder="기본주소">
								<label for="reg_mb_addr1" class="input-group-text">기본주소<?php echo $config['cf_req_addr'] ? ' <i class="bi bi-patch-check"></i>' : ''; ?></label>
							</div>
							<div class="input-group">
								<input type="text" name="mb_addr2" value="<?php echo get_text($member['mb_addr2']) ?>" id="reg_mb_addr2" class="form-control frm_address " size="50" placeholder="상세주소">
								<label for="reg_mb_addr2" class="input-group-text">상세주소</label>
							</div>
							<div class="input-group">
								<input type="text" name="mb_addr3" value="<?php echo get_text($member['mb_addr3']) ?>" id="reg_mb_addr3" class="form-control frm_address " size="50" readonly="readonly" placeholder="참고항목">
								<label for="reg_mb_addr3" class="input-group-text">참고항목</label>
							</div>
							<input type="hidden" name="mb_addr_jibeon" value="<?php echo get_text($member['mb_addr_jibeon']); ?>">
						</div>
					<?php }  ?>
				</section>
				<section class="etc-privacy-info mb-5 border-bottom pb-5">
					<h2 class="fw-bolder fs-5 border-bottom pb-2 mb-2">기타 개인설정</h2>
					<?php if ($config['cf_use_signature']) {  ?>
						<div class="input-group mb-2">
							<label for="reg_mb_signature" class="input-group-text">서명<?php if ($config['cf_req_signature']) { ?> <i class="bi bi-patch-check"></i><?php } ?></label>
							<textarea name="mb_signature" id="reg_mb_signature" <?php echo $config['cf_req_signature'] ? "required" : ""; ?> class="form-control <?php echo $config['cf_req_signature'] ? "required" : ""; ?>" placeholder="서명"><?php echo $member['mb_signature'] ?></textarea>
						</div>
					<?php } ?>

					<?php if ($config['cf_use_profile']) {  ?>
						<div class="input-group mb-2">
							<label for="reg_mb_profile" class="input-group-text">자기소개</label>
							<textarea name="mb_profile" id="reg_mb_profile" <?php echo $config['cf_req_profile'] ? "required" : ""; ?> class="form-control <?php echo $config['cf_req_profile'] ? "required" : ""; ?>" placeholder="자기소개"><?php echo $member['mb_profile'] ?></textarea>
						</div>
					<?php } ?>

					<?php if ($config['cf_use_member_icon'] && $member['mb_level'] >= $config['cf_icon_level']) {  ?>
						<div class="input-group mb-2">
							<label for="reg_mb_icon" class="input-group-text">회원아이콘</label>
							<input type="file" name="mb_icon" class="form-control" id="reg_mb_icon">
							<?php if ($w == 'u' && file_exists($mb_icon_path)) {  ?>
								<span class="input-group-text">
									<img src="<?php echo $mb_icon_url ?>" alt="회원아이콘">
									<input type="checkbox" name="del_mb_icon" value="1" id="del_mb_icon">
									<i class="bi bi-trash-fill"></i>
								</span>
							<?php }  ?>
						</div>
						<div class="alert alert-info">
							<i class="bi bi-info-circle"></i>
							이미지 크기는 가로 <?php echo $config['cf_member_icon_width'] ?>픽셀, 세로 <?php echo $config['cf_member_icon_height'] ?>픽셀 이하로 해주세요.<br>
							gif, jpg, png파일만 가능하며 용량 <?php echo number_format($config['cf_member_icon_size']) ?>바이트 이하만 등록됩니다.
						</div>

					<?php }  ?>

					<?php if ($member['mb_level'] >= $config['cf_icon_level'] && $config['cf_member_img_size'] && $config['cf_member_img_width'] && $config['cf_member_img_height']) {  ?>
						<div class="input-group mb-2">
							<label for="reg_mb_img" class="input-group-text">회원이미지</label>
							<input type="file" name="mb_img" id="reg_mb_img" class="form-control">
							<?php if ($w == 'u' && file_exists($mb_img_path)) {  ?>
								<span class="input-group-text">
									<img src="<?php echo $mb_img_url ?>" alt="회원이미지" class="member-image">
									<input type="checkbox" name="del_mb_img" value="1" id="del_mb_img">
									<i class="bi bi-trash-fill"></i>
								</span>
							<?php }  ?>
						</div>
						<div class="alert alert-info">
							<i class="bi bi-info-circle"></i>
							이미지 크기는 가로 <?php echo $config['cf_member_img_width'] ?>픽셀, 세로 <?php echo $config['cf_member_img_height'] ?>픽셀 이하로 해주세요.<br>
							gif, jpg, png파일만 가능하며 용량 <?php echo number_format($config['cf_member_img_size']) ?>바이트 이하만 등록됩니다.
						</div>
					<?php } ?>
					<div class='checkbox-group'>
						<input type="checkbox" name="mb_mailling" value="1" id="reg_mb_mailling" <?php echo ($w == '' || $member['mb_mailling']) ? 'checked' : ''; ?> class="selec_chk">
						<label for="reg_mb_mailling">
							<span></span>
							<b class="visually-hidden">메일링서비스</b>
						</label>
						<span class="chk_li">정보 메일을 받겠습니다.</span>
					</div>
					<?php if ($config['cf_use_hp']) { ?>
						<div class='checkbox-group'>
							<input type="checkbox" name="mb_sms" value="1" id="reg_mb_sms" <?php echo ($w == '' || $member['mb_sms']) ? 'checked' : ''; ?> class="selec_chk">
							<label for="reg_mb_sms">
								<span></span>
								<b class="visually-hidden">SMS 수신여부</b>
							</label>
							<span class="chk_li">휴대폰 문자메세지를 받겠습니다.</span>
						</div>
					<?php } ?>

					<?php if (isset($member['mb_open_date']) && $member['mb_open_date'] <= date("Y-m-d", G5_SERVER_TIME - ($config['cf_open_modify'] * 86400)) || empty($member['mb_open_date'])) { ?>
						<div class="checkbox-group">
							<input type="checkbox" name="mb_open" value="1" id="reg_mb_open" <?php echo ($w == '' || $member['mb_open']) ? 'checked' : ''; ?> class="selec_chk">
							<label for="reg_mb_open">
								<span></span>
								<b class="visually-hidden">정보공개</b>
							</label>
							<span class="chk_li">다른분들이 나의 정보를 볼 수 있도록 합니다.</span>
							<div class="alert alert-info">
								<i class="bi bi-info-circle"></i>
								정보공개를 바꾸시면 앞으로 <?php echo (int)$config['cf_open_modify'] ?>일 이내에는 변경이 안됩니다.
							</div>
							<input type="hidden" name="mb_open_default" value="<?php echo $member['mb_open'] ?>">
						</div>
					<?php } else { ?>
						<div class="checkbox-group">
							&middot; 정보공개
							<input type="hidden" name="mb_open" value="<?php echo $member['mb_open'] ?>">
							<div class="alert alert-info">
								<i class="bi bi-info-circle"></i>
								정보공개는 수정후 <?php echo (int)$config['cf_open_modify'] ?>일 이내, <?php echo date("Y년 m월 j일", isset($member['mb_open_date']) ? strtotime("{$member['mb_open_date']} 00:00:00") + $config['cf_open_modify'] * 86400 : G5_SERVER_TIME + $config['cf_open_modify'] * 86400); ?> 까지는 변경이 안됩니다.<br>
								이렇게 하는 이유는 잦은 정보공개 수정으로 인하여 쪽지를 보낸 후 받지 않는 경우를 막기 위해서 입니다.
							</div>
						</div>
					<?php }  ?>

					<?php
					//회원정보 수정인 경우 소셜 계정 출력
					if ($w == 'u' && function_exists('social_member_provider_manage')) {
						social_member_provider_manage();
					}
					?>

					<?php if ($w == "" && $config['cf_use_recommend']) {  ?>
						<div class="input-group mb-2">
							<label for="reg_mb_recommend" class="input-group-text">추천인아이디</label>
							<input type="text" name="mb_recommend" id="reg_mb_recommend" class="form-control" placeholder="추천인아이디">
						</div>
					<?php }  ?>

					<div class="captcha-wrap mt-5">
						<?php
						$captcha_html = captcha_html();
						$captcha_html = str_replace('id="captcha_mp3"', 'id="captcha_mp3" class="btn btn-secondary"', $captcha_html);
						$captcha_html = str_replace('id="captcha_reload"', 'id="captcha_reload" class="btn btn-secondary"', $captcha_html);
						$captcha_html = str_replace('class="captcha_box required"', 'class="captcha_box required form-control"', $captcha_html);
						echo $captcha_html;
						?>
					</div>
				</section>
				<div class="d-flex border-top mt-5 mb-5 pt-5">
					<a href="<?php echo G5_URL ?>" class="btn btn-outline-danger">취소</a>
					<button type="submit" id="btn_submit" class="btn btn-outline-primary ms-auto" accesskey="s"><?php echo $w == '' ? '회원가입' : '정보수정'; ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
	$(function() {
		$("#reg_zip_find").css("display", "inline-block");
		var pageTypeParam = "pageType=register";

		<?php if ($config['cf_cert_use'] && $config['cf_cert_simple']) { ?>
			// 이니시스 간편인증
			var url = "<?php echo G5_INICERT_URL; ?>/ini_request.php";
			var type = "";
			var params = "";
			var request_url = "";

			$(".win_sa_cert").click(function() {
				if (!cert_confirm()) return false;
				type = $(this).data("type");
				params = "?directAgency=" + type + "&" + pageTypeParam;
				request_url = url + params;
				call_sa(request_url);
			});
		<?php } ?>
		<?php if ($config['cf_cert_use'] && $config['cf_cert_ipin']) { ?>
			// 아이핀인증
			var params = "";
			$("#win_ipin_cert").click(function() {
				if (!cert_confirm()) return false;
				params = "?" + pageTypeParam;
				var url = "<?php echo G5_OKNAME_URL; ?>/ipin1.php" + params;
				certify_win_open('kcb-ipin', url);
				return;
			});

		<?php } ?>
		<?php if ($config['cf_cert_use'] && $config['cf_cert_hp']) { ?>
			// 휴대폰인증
			var params = "";
			$("#win_hp_cert").click(function() {
				if (!cert_confirm()) return false;
				params = "?" + pageTypeParam;
				<?php
				switch ($config['cf_cert_hp']) {
					case 'kcb':
						$cert_url = G5_OKNAME_URL . '/hpcert1.php';
						$cert_type = 'kcb-hp';
						break;
					case 'kcp':
						$cert_url = G5_KCPCERT_URL . '/kcpcert_form.php';
						$cert_type = 'kcp-hp';
						break;
					case 'lg':
						$cert_url = G5_LGXPAY_URL . '/AuthOnlyReq.php';
						$cert_type = 'lg-hp';
						break;
					default:
						echo 'alert("기본환경설정에서 휴대폰 본인확인 설정을 해주십시오");';
						echo 'return false;';
						break;
				}
				?>

				certify_win_open("<?php echo $cert_type; ?>", "<?php echo $cert_url; ?>" + params);
				return;
			});
		<?php } ?>
	});

	// submit 최종 폼체크
	function fregisterform_submit(f) {
		// 회원아이디 검사
		if (f.w.value == "") {
			var msg = reg_mb_id_check();
			if (msg) {
				alert(msg);
				f.mb_id.select();
				return false;
			}
		}

		if (f.w.value == "") {
			if (f.mb_password.value.length < 3) {
				alert("비밀번호를 3글자 이상 입력하십시오.");
				f.mb_password.focus();
				return false;
			}
		}

		if (f.mb_password.value != f.mb_password_re.value) {
			alert("비밀번호가 같지 않습니다.");
			f.mb_password_re.focus();
			return false;
		}

		if (f.mb_password.value.length > 0) {
			if (f.mb_password_re.value.length < 3) {
				alert("비밀번호를 3글자 이상 입력하십시오.");
				f.mb_password_re.focus();
				return false;
			}
		}

		// 이름 검사
		if (f.w.value == "") {
			if (f.mb_name.value.length < 1) {
				alert("이름을 입력하십시오.");
				f.mb_name.focus();
				return false;
			}

			/*
			var pattern = /([^가-힣\x20])/i;
			if (pattern.test(f.mb_name.value)) {
			    alert("이름은 한글로 입력하십시오.");
			    f.mb_name.select();
			    return false;
			}
			*/
		}

		<?php if ($w == '' && $config['cf_cert_use'] && $config['cf_cert_req']) { ?>
			// 본인확인 체크
			if (f.cert_no.value == "") {
				alert("회원가입을 위해서는 본인확인을 해주셔야 합니다.");
				return false;
			}
		<?php } ?>

		// 닉네임 검사
		if ((f.w.value == "") || (f.w.value == "u" && f.mb_nick.defaultValue != f.mb_nick.value)) {
			var msg = reg_mb_nick_check();
			if (msg) {
				alert(msg);
				f.reg_mb_nick.select();
				return false;
			}
		}

		// E-mail 검사
		if ((f.w.value == "") || (f.w.value == "u" && f.mb_email.defaultValue != f.mb_email.value)) {
			var msg = reg_mb_email_check();
			if (msg) {
				alert(msg);
				f.reg_mb_email.select();
				return false;
			}
		}

		<?php if (($config['cf_use_hp'] || $config['cf_cert_hp']) && $config['cf_req_hp']) {  ?>
			// 휴대폰번호 체크
			var msg = reg_mb_hp_check();
			if (msg) {
				alert(msg);
				f.reg_mb_hp.select();
				return false;
			}
		<?php } ?>

		if (typeof f.mb_icon != "undefined") {
			if (f.mb_icon.value) {
				if (!f.mb_icon.value.toLowerCase().match(/.(gif|jpe?g|png)$/i)) {
					alert("회원아이콘이 이미지 파일이 아닙니다.");
					f.mb_icon.focus();
					return false;
				}
			}
		}

		if (typeof f.mb_img != "undefined") {
			if (f.mb_img.value) {
				if (!f.mb_img.value.toLowerCase().match(/.(gif|jpe?g|png)$/i)) {
					alert("회원이미지가 이미지 파일이 아닙니다.");
					f.mb_img.focus();
					return false;
				}
			}
		}

		if (typeof(f.mb_recommend) != "undefined" && f.mb_recommend.value) {
			if (f.mb_id.value == f.mb_recommend.value) {
				alert("본인을 추천할 수 없습니다.");
				f.mb_recommend.focus();
				return false;
			}

			var msg = reg_mb_recommend_check();
			if (msg) {
				alert(msg);
				f.mb_recommend.select();
				return false;
			}
		}

		<?php echo chk_captcha_js();  ?>

		document.getElementById("btn_submit").disabled = "disabled";

		return true;
	}

	jQuery(function($) {
		//tooltip
		$(document).on("click", ".tooltip_icon", function(e) {
			$(this).next(".tooltip").fadeIn(400).css("display", "inline-block");
		}).on("mouseout", ".tooltip_icon", function(e) {
			$(this).next(".tooltip").fadeOut();
		});
	});
</script>