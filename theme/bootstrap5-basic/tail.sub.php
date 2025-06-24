<?php
if (!defined('_GNUBOARD_')) {
    exit; // 개별 페이지 접근 불가
}
?>

<?php run_event('tail_sub'); ?>

<?php if (!strpos($_SERVER['PHP_SELF'], 'write.php')) { ?>
    <script>
        $(document).ready(function() {
            //smart sticky header, 글쓰기에는 사용하지 않음
            $(".main-header").mhead({
                scroll: {
                    hide: 200
                },
                hooks: {
                    'scrolledIn': function() {
                        //console.log('scrolledIn');
                    },
                    'scrolledOut': function() {
                        //console.log('scrolledOut');
                    }
                }
            });
        });
    </script>
<?php } ?>
</body>

</html>
<?php

//모바일 사용하지 않음
if (G5_IS_MOBILE) {
    include_once G5_THEME_MOBILE_PATH . '/index.php';
    return;
}
echo html_end(); // HTML 마지막 처리 함수 : 반드시 넣어주시기 바랍니다.
