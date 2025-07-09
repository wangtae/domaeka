<?php

/**
 * Bootstrap5-basic 테마용 라이브러리
 */
if (!defined('_GNUBOARD_')) {
    exit;
}
if (file_exists(G5_THEME_PATH . '/vendor/autoload.php')) {
    //composer
    //include_once G5_THEME_PATH . '/vendor/autoload.php';
}
//더미 테이블용.
define("BP_DUMMY_TABLE", 'boilerplate_dummy_log');

/**
 * seoConfig
 */
class seoConfig
{
    public $g5;
    public $config;
    public $member;
    public $board;
    public $group;
    public $bo_table;
    public $wr_id;
    public $is_admin;
    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        global $g5, $config, $member, $board, $group, $bo_table, $wr_id, $is_admin;
        //초기화
        $this->g5 = $g5;
        $this->config = $config;
        $this->member = $member;
        $this->board = $board;
        $this->group = $group;
        $this->bo_table = $bo_table;
        $this->wr_id = $wr_id;
        $this->is_admin = $is_admin;
    }
}

/**
 * 게사판 정보
 */
class Board
{

    protected $_config;

    public function __construct(seoConfig $_config)
    {
        $this->_config = $_config;
    }

    /**
     * 게시물 본문 가져오기
     * @return string
     */
    public function get_article_info($bo_table, $wr_id)
    {
        $bo_table = Asktools::xss_clean($bo_table);
        $wr_id = Asktools::xss_clean($wr_id);

        $sql = "SELECT * from `" . $this->_config->g5['write_prefix'] . $bo_table . "` where `wr_id` = '{$wr_id}'";
        $result = sql_fetch($sql);
        return $result;
    }

    /**
     * 게시판정보 가져오기
     */
    public function get_board_info($bo_table)
    {
        $bo_table = Asktools::xss_clean($bo_table);
        $sql = "select * from `" . $this->_config->g5['board_table'] . "` where `bo_table` = '{$bo_table}'";
        $result = sql_fetch($sql);
        return $result;
    }

    /**
     * 메모 정보 가져오기
     */
    public function get_memo_info($me_id)
    {
        $me_id = Asktools::xss_clean($me_id);
        $sql = "select * from `" . $this->_config->g5['memo_table'] . "` where `me_id` = '{$me_id}'";
        $result = sql_fetch($sql);
        return $result;
    }

    /**
     * 테이블 유무 검사
     */
    public function exsit_table($table_name)
    {
        $sql = "SELECT EXISTS ( SELECT 1 FROM Information_schema.tables WHERE table_name = '" . $table_name . "' AND table_schema = '" . G5_MYSQL_DB . "' ) AS flag";
        $result = sql_fetch($sql);
        if ($result['flag'] == 1) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 인기게시물 뽑기 - board_new 테이블에서 뽑기.
     */
    public function get_popular_item($bo_table, $days = 7)
    {
        $date = date("Y-m-d", strtotime("-1 week"));
        $sql = " select a.*, b.bo_subject, c.wr_subject, c.wr_hit, c.wr_good, c.wr_comment from g5_board_new a left join  g5_board b  on a.bo_table = b.bo_table left join  {$g5['write_prefix']}{$bo_table} c
                on a.wr_id = c.wr_id where  a.bo_table = '{$bo_table}' and  b.bo_use_search = 1 and a.bn_datetime > '{$date}' and c.wr_is_comment = '0' order by c.wr_good desc, c.wr_hit desc, c.wr_comment desc limit 5";
        $result = sql_query($sql);
        while ($rows = sql_fetch_array($result)) {
            $data[] = $rows;
        }
        return $data;
    }

    /**
     * 게시판 목록
     */
    public function get_board_list()
    {

        global $is_admin, $member;
        //대상 게시판 
        $sql = " SELECT * from " . $this->_config->g5['board_table'] . " a, " . $this->_config->g5['group_table'] . " b where a.gr_id = b.gr_id ";
        if ($is_admin == 'group') {
            $sql .= " and b.gr_admin = '{$member['mb_id']}' ";
        } else if ($is_admin == 'board') {
            $sql .= " and a.bo_admin = '{$member['mb_id']}' ";
        }
        $sql .= " order by a.gr_id, a.bo_order, a.bo_table ";

        $result = sql_query($sql);
        for ($i = 0; $row = sql_fetch_array($result); $i++) {
            $list[$i] = $row;
        }
        return $list;
    }

    /**
     * 첨부파일 정보
     *
     * @param [type] $bo_table
     * @param [type] $wr_id
     * @return void
     */
    public function get_file($bo_table, $wr_id, $bf_no)
    {
        global $g5, $qstr;

        $file['count'] = 0;
        $sql = " SELECT * from `{$this->_config->g5['board_file_table']}` where `bo_table` = '{$bo_table}' and `wr_id` = '{$wr_id}' and `bf_no` = '{$bf_no}' ";
        $file = sql_fetch($sql);
        return $file;
    }

    /**
     * 내 최신 스크랩
     *
     * @param integer $limit
     * @return void
     */
    public function get_myscrap($limit = 5)
    {
        global $member;

        $sql_common = " from {$this->_config->g5['scrap_table']} where mb_id = '{$member['mb_id']}' ";
        $sql_order = " order by ms_id desc ";
        $list = array();

        $sql = " select * $sql_common $sql_order  limit $limit ";
        $result = sql_query($sql);
        for ($i = 0; $row = sql_fetch_array($result); $i++) {

            $list[$i] = $row;

            // 게시판 제목
            $sql2 = " select bo_subject from {$this->cfg['board_table']} where bo_table = '{$row['bo_table']}' ";
            $row2 = sql_fetch($sql2);
            if (!$row2['bo_subject'])
                $row2['bo_subject'] = '[게시판 없음]';

            // 게시물 제목
            $tmp_write_table = $this->_config->g5['write_prefix'] . $row['bo_table'];
            $sql3 = " select wr_subject from $tmp_write_table where wr_id = '{$row['wr_id']}' ";
            $row3 = sql_fetch($sql3, FALSE);
            $subject = get_text(cut_str($row3['wr_subject'], 30));
            if (!$row3['wr_subject'])
                $row3['wr_subject'] = '[글 없음]';

            $list[$i]['opener_href'] = get_pretty_url($row['bo_table']);
            $list[$i]['opener_href_wr_id'] = get_pretty_url($row['bo_table'], $row['wr_id']);
            $list[$i]['bo_subject'] = $row2['bo_subject'];
            $list[$i]['subject'] = $subject;
        }
        return $list;
    }
    /**
     * 내 포인트 내역
     *
     * @param integer $limit
     * @return void
     */
    public function get_mypoint($limit = 5)
    {
        global $member;
        $sql_common = " from {$this->_config->g5['point_table']} where mb_id = '" . escape_trim($member['mb_id']) . "' ";
        $sql_order = " order by po_id desc ";

        $sql = " SELECT * {$sql_common} {$sql_order} limit $limit ";
        $result = sql_query($sql);
        $list = array();
        for ($i = 0; $row = sql_fetch_array($result); $i++) {
            $point1 = $point2 = 0;
            $list[$i]['point_use_class'] = '';
            if ($row['po_point'] > 0) {
                $point1 = '+' . number_format($row['po_point']);
                $list[$i]['point1'] = $point1;
            } else {
                $point2 = number_format($row['po_point']);
                $list[$i]['point2'] = $point2;
                $list[$i]['point_use_class'] = 'point_use';
            }

            $list[$i]['po_content'] = $row['po_content'];

            $list[$i]['expired_class'] = '';
            if ($row['po_expired'] == 1) {
                $list[$i]['expired_class'] = ' disabled';
            }
            $list[$i]['po_expire_date'] = '';
            if ($row['po_expired'] == 1) {
                $list[$i]['po_expire_date'] = "만료 " . substr(str_replace('-', '', $row['po_expire_date']), 2);
            } else {
                $list[$i]['po_expire_date'] == '9999-12-31' ? '&nbsp;' : $row['po_expire_date'];
            };
        } //for
        return $list;
    }

    /**
     * 받은 최신쪽지
     *
     * @param integer $limit
     * @return void
     */
    public function get_mymemo($limit = 5)
    {
        global $member;

        $sql = " SELECT * from {$this->_config->g5['memo_table']} where `me_type`='recv' and `me_recv_mb_id` = '" . escape_trim($member['mb_id']) . "' order by me_id desc limit {$limit}";
        $result = sql_query($sql);
        for ($i = 0; $row = sql_fetch_array($result); $i++) {
            $list[$i] = $row;

            if (substr($row['me_read_datetime'], 0, 1) == 0) {
                $is_read = '<i class="fa fa-envelope" aria-hidden="true"></i>';
            } else {
                $is_read = '<i class="fa fa-envelope-open-o" aria-hidden="true"></i>';
            }
            $list[$i]['is_read'] = $is_read;
            $list[$i]['view_href'] = G5_BBS_URL . '/memo_view.php?me_id=' . $row['me_id'] . '&amp;kind=recv';
        }
        return $list;
    }
    /**
     * 회원 최근게시물
     *
     * @param [type] $limit
     * @return void
     */
    public function get_mylatest($limit = 5)
    {
        global $member, $config;
        $sql_common = " from {$this->_config->g5['board_new_table']} a, {$this->_config->g5['board_table']} b, {$this->_config->g5['group_table']} c where a.bo_table = b.bo_table and b.gr_id = c.gr_id and b.bo_use_search = 1 ";
        $sql_common .= " and a.mb_id = '" . escape_trim($member['mb_id']) . "' ";
        $sql_order = " order by a.bn_id desc ";

        $list = array();
        $sql = " SELECT a.*, b.bo_subject, b.bo_mobile_subject, c.gr_subject, c.gr_id {$sql_common} {$sql_order} limit {$limit} ";
        $result = sql_query($sql);
        for ($i = 0; $row = sql_fetch_array($result); $i++) {
            $tmp_write_table = $this->cfg['write_prefix'] . $row['bo_table'];

            if ($row['wr_id'] == $row['wr_parent']) {

                // 원글
                $comment = "";
                $comment_link = "";
                $row2 = sql_fetch(" SELECT * from {$tmp_write_table} where wr_id = '{$row['wr_id']}' ");
                $list[$i] = $row2;

                $name = get_sideview($row2['mb_id'], get_text(cut_str($row2['wr_name'], $config['cf_cut_name'])), $row2['wr_email'], $row2['wr_homepage']);
                // 당일인 경우 시간으로 표시함
                $datetime = substr($row2['wr_datetime'], 0, 10);
                $datetime2 = $row2['wr_datetime'];
                if ($datetime == G5_TIME_YMD) {
                    $datetime2 = substr($datetime2, 11, 5);
                } else {
                    $datetime2 = substr($datetime2, 5, 5);
                }
            } else {

                // 코멘트
                $comment = '[코] ';
                $comment_link = '#c_' . $row['wr_id'];
                $row2 = sql_fetch(" SELECT * from {$tmp_write_table} where wr_id = '{$row['wr_parent']}' ");
                $row3 = sql_fetch(" SELECT mb_id, wr_name, wr_email, wr_homepage, wr_datetime from {$tmp_write_table} where wr_id = '{$row['wr_id']}' ");
                $list[$i] = $row2;
                $list[$i]['wr_id'] = $row['wr_id'];
                $list[$i]['mb_id'] = $row3['mb_id'];
                $list[$i]['wr_name'] = $row3['wr_name'];
                $list[$i]['wr_email'] = $row3['wr_email'];
                $list[$i]['wr_homepage'] = $row3['wr_homepage'];

                $name = get_sideview($row3['mb_id'], get_text(cut_str($row3['wr_name'], $config['cf_cut_name'])), $row3['wr_email'], $row3['wr_homepage']);
                // 당일인 경우 시간으로 표시함
                $datetime = substr($row3['wr_datetime'], 0, 10);
                $datetime2 = $row3['wr_datetime'];
                if ($datetime == G5_TIME_YMD) {
                    $datetime2 = substr($datetime2, 11, 5);
                } else {
                    $datetime2 = substr($datetime2, 5, 5);
                }
            }

            $list[$i]['gr_id'] = $row['gr_id'];
            $list[$i]['bo_table'] = $row['bo_table'];
            $list[$i]['name'] = $name;
            $list[$i]['comment'] = $comment;
            $list[$i]['href'] = get_pretty_url($row['bo_table'], $row2['wr_id'], $comment_link);
            $list[$i]['datetime'] = $datetime;
            $list[$i]['datetime2'] = $datetime2;

            $list[$i]['gr_subject'] = $row['gr_subject'];
            $list[$i]['bo_subject'] = ((G5_IS_MOBILE && $row['bo_mobile_subject']) ? $row['bo_mobile_subject'] : $row['bo_subject']);
            $list[$i]['wr_subject'] = $row2['wr_subject'];
        }
        return $list;
    }
}




/**
 * ASK Member tools
 */
class Asktools
{

    /**
     * $5 var
     * @var mixed
     */
    static $g5;

    /**
     * 기본환경설정
     * @var mixed 
     */
    static $config;

    /**
     * 회원정보
     * @var mixed 
     */
    static $member;

    /**
     * 게시판
     * @var mixed 
     */
    static $board;

    /**
     * 그룹
     * @var mixed 
     */
    static $group;

    /**
     * bo_table
     * @var mixed 
     */
    static $bo_table;

    /**
     * wr_id
     * @var mixed 
     */
    static $wr_id;

    /**
     * 현재페이지
     * @var mixed 
     */
    private static $currentPage;

    /**
     * 관리자
     * @var mixed 
     */
    private static $is_admin;

    /**
     * 초기화
     * @global mixed $member
     */
    public static function init()
    {
        global $g5, $config, $member, $board, $group, $bo_table, $wr_id, $is_admin;
        //초기화
        self::$g5 = $g5;
        self::$config = $config;
        self::$member = $member;
        self::$board = $board;
        self::$group = $group;
        self::$bo_table = $bo_table;
        self::$wr_id = $wr_id;
        self::$is_admin = $is_admin;
    }

    /**
     * 리다이렉트
     * @param mixed $uri
     * @param string $method
     * @param mixed $code
     */
    public static function redirect($uri = '', $method = 'auto', $code = NULL)
    {
        // IIS environment likely? Use 'refresh' for better compatibility
        if ($method === 'auto' && isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== FALSE) {
            $method = 'refresh';
        } elseif ($method !== 'refresh' && (empty($code) or !is_numeric($code))) {
            if (isset($_SERVER['SERVER_PROTOCOL'], $_SERVER['REQUEST_METHOD']) && $_SERVER['SERVER_PROTOCOL'] === 'HTTP/1.1') {
                $code = ($_SERVER['REQUEST_METHOD'] !== 'GET') ? 303 // reference: http://en.wikipedia.org/wiki/Post/Redirect/Get
                    : 307;
            } else {
                $code = 302;
            }
        }

        switch ($method) {
            case 'refresh':
                header('Refresh:0;url=' . $uri);
                break;
            default:
                header('Location: ' . $uri, TRUE, $code);
                break;
        }
        exit;
    }

    /**
     * xss 제거
     * @param mixed $data
     * @return mixed
     */
    public static function xss_clean($data)
    {
        return filter_var($data, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }

    /**
     * HTML 필터링
     * @param mixed $valor
     * @return string
     */
    public function htmlFilter($valor)
    {
        $resultado = htmlentities($valor, ENT_QUOTES, 'UTF-8');
        return $resultado;
    }

    /**
     * SQL 필터링
     * @param mixed $valor
     * @return array|string
     */
    public function SQLfilter($valor)
    {
        $valor = str_ireplace("SELECT", "", $valor);
        $valor = str_ireplace("COPY", "", $valor);
        $valor = str_ireplace("DELETE", "", $valor);
        $valor = str_ireplace("DROP", "", $valor);
        $valor = str_ireplace("DUMP", "", $valor);
        $valor = str_ireplace(" OR ", "", $valor);
        $valor = str_ireplace("%", "", $valor);
        $valor = str_ireplace("LIKE", "", $valor);
        $valor = str_ireplace("--", "", $valor);
        $valor = str_ireplace("^", "", $valor);
        $valor = str_ireplace("[", "", $valor);
        $valor = str_ireplace("]", "", $valor);
        $valor = str_ireplace("\\", "", $valor);
        $valor = str_ireplace("!", "", $valor);
        $valor = str_ireplace("¡", "", $valor);
        $valor = str_ireplace("?", "", $valor);
        $valor = str_ireplace("=", "", $valor);
        $valor = str_ireplace("&", "", $valor);
        return $valor;
    }

    /**
     * 
     * @param mixed $data
     * @return mixed
     */
    public function xssFilter($data)
    {
        // Fix &entity\n;
        $data = str_replace(array('&amp;', '&lt;', '&gt;'), array('&amp;amp;', '&amp;lt;', '&amp;gt;'), $data);
        $data = preg_replace('/(&#*\w+)[\x00-\x20]+;/u', '$1;', $data);
        $data = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $data);
        $data = html_entity_decode($data, ENT_COMPAT, 'UTF-8');
        // Remove any attribute starting with "on" or xmlns
        $data = preg_replace('#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu', '$1>', $data);
        // Remove javascript: and vbscript: protocols
        $data = preg_replace('#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2nojavascript...', $data);
        $data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2novbscript...', $data);
        $data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u', '$1=$2nomozbinding...', $data);
        // Only works in IE: <span style="width: expression(alert('Ping!'));"></span>
        $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?expression[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
        $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?behaviour[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
        $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:*[^>]*+>#iu', '$1>', $data);
        // Remove namespaced elements (we do not need them)
        $data = preg_replace('#</*\w+:\w[^>]*+>#i', '', $data);
        do {
            // Remove really unwanted tags
            $old_data = $data;
            $data = preg_replace('#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i', '', $data);
        } while ($old_data !== $data);
        // we are done...
        return $data;
    }

    /**
     * 필터
     * @param mixed $data
     * @return mixed
     */
    public static function filter($data)
    {
        self::SQLfilter($data);
        self::xssFilter($data);
        self::htmlFilter($data);

        return $data;
    }

    /**
     * 필터링
     * @param mixed $data
     * @return mixed
     */
    public static function clean($data)
    {

        self::filter($data);

        return $data;
    }
}



/**
 * DB 생성용.
 */
class DBInstall
{
    /**
     * dummy 게시물 로그
     * @param mixed $table_name
     * @return string
     */
    public static function db_dummy($table_name)
    {
        //테이블 체크
        if ($_board->exsit_table($table_name) == false) {
            $_table_query = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
                `dm_idx` int(11) NOT NULL ,
                `dm_bo_table` varchar(50) NOT NULL,
                `dm_wr_id` int(11) NOT NULL
              ) ENGINE=MyISAM DEFAULT CHARSET=" . G5_DB_CHARSET . ";";
            sql_query($_table_query, true);
            sql_query("ALTER TABLE `{$table_name}` ADD PRIMARY KEY (`dm_idx`);", true);
            sql_query("ALTER TABLE `{$table_name}` MODIFY `dm_idx` int(11) NOT NULL AUTO_INCREMENT;", true);
            sql_query("ALTER TABLE `{$table_name}` ADD INDEX `dummygroup` (`dm_bo_table`, `dm_wr_id`)", true);

            //설치완료
            $result = "<div class='alert alert-info'><i class=\"fa fa-info\" aria-hidden=\"true\"></i> dummy 게시물 로그 DB(" . $table_name . ") 설치 완료!</div>";
        } else {
            //이미설치완료
            $result = "<div class='alert alert-warning'><i class=\"fa fa-info-circle\" aria-hidden=\"true\"></i> dummy 게시물 로그 DB(" . $table_name . ") 이미 설치되었습니다.</div>";
        }
        return $result;
    }
}



if (!function_exists('bp_display_message')) {

    /**
     * 처리결과 메세지 출력용
     *
     * @return void
     */
    function bp_display_message(): string
    {
        if (!isset($_SESSION['message'])) {
            return '';
        } else {
            $str = "
            <div class='alert-message alert alert-info alert-dismissible fade show' role='alert'>
            <h3><i class='fa fa-info-circle' aria-hidden='true'></i></strong> Message </h3>
            {$_SESSION['message']}
            <button type='button' class='message-close close' data-dismiss='alert' aria-label='Close'>
                <span aria-hidden='true'>&times;</span>
            </button>
            </div>
            <script>
                $('.message-close').click(function() {
                    $('.alert-message').hide();
                });
            </script>";
            unset($_SESSION['message']);
            return $str;
        }
    }
}

if (!function_exists('comment_file_no_sort')) {
    /**
     * 첨부파일 순서 재설정
     * @param mixed $bo_table
     * @param mixed $wr_id
     * @return void
     */
    function comment_file_no_sort($bo_table, $wr_id)
    {
        global $g5;
        //파일순서 리빌드
        $sql = "SELECT * from {$g5['board_file_table']} where bo_table = '{$bo_table}' and wr_id = '{$wr_id}' order by bf_datetime";
        $res = sql_query($sql);
        for ($i = 0; $rows = sql_fetch_array($res); $i++) {
            $sql = "UPDATE {$g5['board_file_table']} set bf_no = '{$i}' where bo_table = '{$rows['bo_table']}' and wr_id = '{$rows['wr_id']}' and bf_no = '{$rows['bf_no']}' limit 1";
            sql_query($sql);
        }
    }
}

/**
 * 
 * 게시판 정보등을 불러오는 클래스 Factory
 */
class BoardFactory
{
    public static function create()
    {
        $_gnuboardconfig = new seoConfig;
        return new Board($_gnuboardconfig);
    }
}

$_board = BoardFactory::create();




/**
 * 짧은주소 지원 게시판 정렬 기능
 * @param mixed $col
 * @param mixed $query_string
 * @param mixed $flag
 * @return string
 */
function askseko_subject_sort_link($col, $query_string = '', $flag = 'asc')
{
    global $sst, $sod, $sfl, $stx, $page, $sca, $bo_table;

    $q1 = "sst=$col";
    if ($flag == 'asc') {
        $q2 = 'sod=asc';
        if ($sst == $col) {
            if ($sod == 'asc') {
                $q2 = 'sod=desc';
            }
        }
    } else {
        $q2 = 'sod=desc';
        if ($sst == $col) {
            if ($sod == 'desc') {
                $q2 = 'sod=asc';
            }
        }
    }

    $arr_query = array();
    $arr_query[] = $query_string;
    $arr_query[] = $q1;
    $arr_query[] = $q2;
    $arr_query[] = 'sfl=' . $sfl;
    $arr_query[] = 'stx=' . $stx;
    $arr_query[] = 'sca=' . $sca;
    $arr_query[] = 'page=' . $page;
    $qstr = implode("&amp;", $arr_query);
    $url = G5_HTTP_BBS_URL . "/board.php?{$qstr}";
    return short_url_clean($url);
}

if (!function_exists('print_t')) {
    /**
     * textarea로 보기
     * @param mixed $str
     * @return void
     */
    function print_t($str)
    {
        echo "<textarea style='width:100%; height:500px;'>";
        print_r($str);
        echo "</textarea>";
    }
}

if (!function_exists('bp_get_hostname')) {
    /**
     *  사이트 URL
     */
    function bp_get_hostname()
    {
        if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) {
            $protocol = 'https://';
        } else {
            $protocol = 'http://';
        }
        //cloudflare 사용시 처리
        if (isset($_SERVER['HTTP_CF_VISITOR']) && $_SERVER['HTTP_CF_VISITOR']) {
            if (json_decode($_SERVER['HTTP_CF_VISITOR'])->scheme == 'https')
                $_SERVER['HTTPS'] = 'on';
            $protocol = 'https://';
        }

        $domainName = $_SERVER['HTTP_HOST'];
        return $protocol . $domainName;
    }
}



if (!function_exists('_display_code_view')) {
    /**
     * code view
     * @param mixed $id
     * @param mixed $code
     * @return string
     */
    function _display_code_view($id, $code)
    {
        $code = htmlspecialchars($code);
        return "<div class='code-view position-relative'><div id='{$id}' class='code'>{$code}</div><script>$(function() {codeViewer('{$id}');})</script></div>";
    }
}


if (!function_exists('bp_image_orientation_fix')) {

    /**
     * 이미지 방향 처리 JPG
     * @param mixed $filename
     * @param mixed $quility
     * @return bool
     */
    function bp_image_orientation_fix($filename, $quility)
    {
        if (function_exists('exif_read_data')) {
            $file_parts = pathinfo($filename);
            //jpg 아니면 
            if (strtolower($file_parts['extension']) != 'jpg') {
                return false;
            }
            $exif = exif_read_data($filename);
            if ($exif && isset($exif['Orientation'])) {
                $orientation = $exif['Orientation'];
                if ($orientation != 1) {
                    $img = imagecreatefromjpeg($filename);
                    $deg = 0;
                    switch ($orientation) {
                        case 3:
                            $deg = 180;
                            break;
                        case 6:
                            $deg = 270;
                            break;
                        case 8:
                            $deg = 90;
                            break;
                    }
                    if ($deg) {
                        $img = imagerotate($img, $deg, 0);
                    }
                    // then rewrite the rotated image back to the disk as $filename 
                    imagejpeg($img, $filename, $quility);
                } // if there is some rotation necessary
            } // if have the exif orientation info
        } // if function exists      
    }
}

//Tinymce 에디터용
define("BP_TINYMCE_DOMAIN", array(bp_get_hostname()));

/************************************************************************************************
 * HOOK용 
 ************************************************************************************************/
if (!function_exists('bb_image_replace')) {
    /**
     * id 속성을 class 로 변경.
     * 이미지에 왜 ID 씀? ㄷㄷㄷ
     * @param mixed $img
     * @return array|string
     */
    function bb_image_replace($img)
    {
        return str_replace('id=', "class=", $img);
    }
    add_replace('get_it_image_tag', 'bb_image_replace', 10, 1);
}
if (!function_exists('html_header_footer')) {
    /**
     * 각종 설정중 상단내용, 하단 내용 태그 감싸기
     * @param mixed $tags
     * @return string
     */
    function html_header_footer($tags)
    {
        if ($tags) {
            $data = "<div class='container'><div class='bg-white p-3 p-lg-4 border mt-2 mb-2'>";
            $data .= $tags;
            $data .= "</div></div>";
            return $data;
        }
    }

    add_replace('board_content_head', 'html_header_footer', 10, 1);
    add_replace('board_content_tail', 'html_header_footer', 10, 1);
    add_replace('qa_content_head', 'html_header_footer', 10, 1);
    add_replace('qa_content_tail', 'html_header_footer', 10, 1);
    add_replace('shop_it_head_html', 'html_header_footer', 10, 1);
    add_replace('shop_it_tail_html', 'html_header_footer', 10, 1);
}

/**
 * CSS 파일 합치기
 * @param mixed $cssfiles
 * @return array
 */
function css_minify($cssfiles)
{
    //theme.config.php 파일에서 설정
    if (defined('BB_CSS_USE_MINIFY') && BB_CSS_USE_MINIFY === true) {
        $item = array();
        $regex = <<<REGEX
    /<link[^>]+href\s*=\s*('|")(?P<link1>[^'"]+?\.css)('|")|{{\s*css\s*\(\s*('|")(?P<link2>[^)'"]+)('|")\s*\)\s*}}/is
REGEX;
        $i = 0;
        foreach ($cssfiles as $link) {
            preg_match($regex, $link[1], $matches);
            if (isset($matches[2])) {
                $_tmp = parse_url($matches[2]);
                $item[$i] = $_tmp['path'];
                $i++;
            }
        }

        $css = implode(',', $item);
        return array(array(0, "<link rel='stylesheet' href='" . BB_ASSETS_URL . "/min/?f={$css}&'>"));
    } else {
        return $cssfiles;
    }
}
add_replace('html_process_css_files', 'css_minify', 10, 5);

/**
 * 스크립트 파일 합치기
 * @param mixed $jsfiles
 * @return array
 */
function js_minify($jsfiles)
{

    //외부파일이 포함된 페이지 제외해야 한다.
    if (stripos($_SERVER['PHP_SELF'], 'register')) {
        return $jsfiles;
    }
    if (stripos($_SERVER['PHP_SELF'], 'orderform')) {
        return $jsfiles;
    }

    //관리자 페이지는 패스~
    if (stripos($_SERVER['PHP_SELF'], G5_ADMIN_DIR)) {
        return $jsfiles;
    }
    //theme.config.php 파일에서 설정
    if (defined('BB_JS_USE_MINIFY') && BB_JS_USE_MINIFY === true) {
        $item = array();
        $regex = <<<REGEX
    /<script[^>]+src\s*=\s*('|")(?P<script1>[^'"]+?\.js)('|")|{{\s*js\s*\(\s*('|")(?P<script2>[^)'"]+)('|")\s*\)\s*}}/is
REGEX;
        $i = 0;
        foreach ($jsfiles as $link) {
            preg_match($regex, $link[1], $matches);
            if (isset($matches[2])) {
                $_tmp = parse_url($matches[2]);
                $item[$i] = $_tmp['path'];
                $i++;
            }
        }
        $js = implode(',', $item);
        return array(array(0, "<script src='" . BB_ASSETS_URL . "/min/?f={$js}&'></script>"));
    } else {
        return $jsfiles;
    }
}
add_replace('html_process_script_files', 'js_minify', 10, 5);
