<?php
$sub_menu = "";
include_once('./_common.php');
include_once(G5_DMK_PATH.'/adm/lib/admin.auth.lib.php');

// 지점 ID 파라미터 가져오기
$br_id = isset($_GET['br_id']) ? clean_xss_tags($_GET['br_id']) : '';

if (!$br_id) {
    alert('지점 정보가 필요합니다.', G5_URL);
}

// 지점 정보 조회
$branch_sql = " SELECT b.*, a.ag_name 
                FROM dmk_branch b 
                LEFT JOIN dmk_agency a ON b.ag_id = a.ag_id 
                WHERE b.br_id = '$br_id' AND b.br_status = 1 ";
$branch = sql_fetch($branch_sql);

if (!$branch) {
    alert('유효하지 않은 지점입니다.', G5_URL);
}

$g5['title'] = $branch['br_name'] . ' 주문페이지';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $g5['title'] ?></title>
    <style>
    * { box-sizing: border-box; }
    body { 
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Noto Sans KR', sans-serif; 
        margin: 0; padding: 0; 
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
    }
    
    .container {
        max-width: 480px;
        margin: 0 auto;
        background: white;
        min-height: 100vh;
        box-shadow: 0 0 20px rgba(0,0,0,0.1);
    }
    
    .header { 
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem 1.5rem; 
        text-align: center; 
        position: relative;
    }
    
    .header::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        width: 0;
        height: 0;
        border-left: 20px solid transparent;
        border-right: 20px solid transparent;
        border-top: 10px solid #764ba2;
    }
    
    .header h1 { 
        margin: 0; 
        font-size: 1.8rem; 
        font-weight: 600;
        text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }
    
    .header .branch-info { 
        margin: 1rem 0 0 0; 
        opacity: 0.9; 
        font-size: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    
    .header .phone-number { 
        margin: 0.8rem 0 0 0; 
        font-size: 1.1rem;
        font-weight: 500;
        background: rgba(255,255,255,0.2);
        padding: 0.5rem 1rem;
        border-radius: 25px;
        display: inline-block;
    }
    
    .quick-actions {
        padding: 1.5rem;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    
    .action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 1.5rem 1rem;
        background: white;
        border: 2px solid #e9ecef;
        border-radius: 15px;
        text-decoration: none;
        color: #333;
        transition: all 0.3s ease;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        min-height: 120px;
        justify-content: center;
    }
    
    .action-btn:hover, .action-btn:active {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        border-color: #667eea;
    }
    
    .action-btn.primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-color: transparent;
    }
    
    .action-btn.success {
        background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
        color: white;
        border-color: transparent;
    }
    
    .action-btn .icon {
        font-size: 2.5rem;
        margin-bottom: 0.5rem;
    }
    
    .action-btn .text {
        font-weight: 600;
        font-size: 1rem;
        text-align: center;
    }
    
    .content { 
        padding: 1.5rem; 
    }
    
    .info-card {
        background: #f8f9fa;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        border-left: 4px solid #667eea;
    }
    
    .info-card h3 {
        margin: 0 0 1rem 0;
        color: #333;
        font-size: 1.2rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .info-item {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0;
        border-bottom: 1px solid #e9ecef;
    }
    
    .info-item:last-child {
        border-bottom: none;
    }
    
    .info-label {
        color: #666;
        font-weight: 500;
    }
    
    .info-value {
        color: #333;
        font-weight: 600;
    }
    
    .notice {
        background: linear-gradient(135deg, #ffeaa7 0%, #fab1a0 100%);
        color: #2d3436;
        padding: 1.5rem;
        border-radius: 15px;
        text-align: center;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .notice h3 {
        margin: 0 0 0.5rem 0;
        font-size: 1.3rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    
    .notice p {
        margin: 0;
        line-height: 1.6;
        font-size: 1rem;
    }
    
    .footer {
        padding: 2rem 1.5rem;
        text-align: center;
        background: #f8f9fa;
        border-top: 1px solid #e9ecef;
        color: #666;
        font-size: 0.9rem;
    }
    
    /* 터치 친화적 개선 */
    @media (max-width: 480px) {
        .container {
            max-width: 100%;
        }
        
        .quick-actions {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .action-btn {
            min-height: 100px;
            padding: 1.2rem 1rem;
        }
        
        .action-btn .icon {
            font-size: 2rem;
        }
        
        .header h1 {
            font-size: 1.6rem;
        }
    }
    
    /* 다크모드 지원 */
    @media (prefers-color-scheme: dark) {
        .container {
            background: #1a1a1a;
        }
        
        .info-card {
            background: #2d2d2d;
            color: #e9ecef;
        }
        
        .info-item {
            border-bottom-color: #444;
        }
        
        .info-label {
            color: #aaa;
        }
        
        .info-value {
            color: #e9ecef;
        }
        
        .footer {
            background: #2d2d2d;
            border-top-color: #444;
            color: #aaa;
        }
    }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo htmlspecialchars($branch['br_name']) ?></h1>
            <div class="branch-info">
                <span>🏢</span>
                <span><?php echo htmlspecialchars($branch['ag_name'] ?: '직영') ?> 소속</span>
            </div>
            <?php if ($branch['br_phone']) { ?>
            <div class="phone-number">📞 <?php echo htmlspecialchars($branch['br_phone']) ?></div>
            <?php } ?>
        </div>
        
        <div class="quick-actions">
            <a href="tel:<?php echo str_replace('-', '', $branch['br_phone']) ?>" class="action-btn primary">
                <div class="icon">📞</div>
                <div class="text">전화 주문</div>
            </a>
            
            <a href="#info" class="action-btn">
                <div class="icon">ℹ️</div>
                <div class="text">지점 정보</div>
            </a>
            
            <a href="<?php echo G5_SHOP_URL ?>" class="action-btn">
                <div class="icon">🛒</div>
                <div class="text">온라인 쇼핑몰</div>
            </a>
            
            <a href="<?php echo G5_URL ?>" class="action-btn success">
                <div class="icon">🏠</div>
                <div class="text">메인으로</div>
            </a>
        </div>
        
        <div class="content">
            <div class="notice">
                <h3>🚧 주문 안내</h3>
                <p>현재 온라인 주문 시스템을 구축 중입니다.<br>
                주문은 전화 또는 온라인 쇼핑몰을 이용해 주세요.</p>
            </div>
            
            <div class="info-card" id="info">
                <h3>📍 지점 정보</h3>
                <div class="info-item">
                    <span class="info-label">지점명</span>
                    <span class="info-value"><?php echo htmlspecialchars($branch['br_name']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">소속 대리점</span>
                    <span class="info-value"><?php echo htmlspecialchars($branch['ag_name'] ?: '직영') ?></span>
                </div>
                <?php if ($branch['br_phone']) { ?>
                <div class="info-item">
                    <span class="info-label">전화번호</span>
                    <span class="info-value"><?php echo htmlspecialchars($branch['br_phone']) ?></span>
                </div>
                <?php } ?>
                <?php if ($branch['br_address']) { ?>
                <div class="info-item">
                    <span class="info-label">주소</span>
                    <span class="info-value"><?php echo htmlspecialchars($branch['br_address']) ?></span>
                </div>
                <?php } ?>
            </div>
        </div>
        
        <div class="footer">
            <p>© <?php echo date('Y') ?> <?php echo htmlspecialchars($branch['br_name']) ?></p>
            <p>도매까 플랫폼 지원</p>
        </div>
    </div>
</body>
</html>
