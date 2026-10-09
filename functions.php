<?php
add_theme_support('title-tag');
register_nav_menus(array('primary' => 'Menu chính'));
function ta_primary_menu() {
    $loc = get_nav_menu_locations();
    $items = wp_get_nav_menu_items($loc['primary']);
    if (!$items) return;
    foreach ($items as $it) {
        echo '<a href="' . esc_url($it->url) . '">' . esc_html($it->title) . '</a>';
    }
}

/* ---------- Database: bảng wp_ta_registrations ---------- */
function ta_table() { global $wpdb; return $wpdb->prefix . 'ta_registrations'; }
function ta_create_table() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $t = ta_table();
    $sql = "CREATE TABLE $t (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        ho_ten VARCHAR(100) NOT NULL,
        sdt VARCHAR(20) NOT NULL,
        email VARCHAR(100) DEFAULT '' NOT NULL,
        khoa_hoc VARCHAR(100) DEFAULT '' NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id)
    ) " . $wpdb->get_charset_collate() . ";";
    dbDelta($sql);
    update_option('ta_db_ver', '1');
}
add_action('after_switch_theme', 'ta_create_table');
add_action('init', function () { if (get_option('ta_db_ver') !== '1') ta_create_table(); });

/* ---------- Nhận form đăng ký ---------- */
function ta_handle_register() {
    $back = add_query_arg('trang', 'lien-he', home_url('/'));
    $go = function ($code) use ($back) { wp_safe_redirect(add_query_arg('dk', $code, $back) . '#contact'); exit; };
    if (!isset($_POST['ta_nonce']) || !wp_verify_nonce($_POST['ta_nonce'], 'ta_register') || !empty($_POST['website'])) $go('0');
    $ten  = trim(sanitize_text_field(wp_unslash($_POST['ho_ten'] ?? '')));
    $raw  = preg_replace('/[^0-9+]/', '', wp_unslash($_POST['sdt'] ?? ''));
    $mail = trim(wp_unslash($_POST['email'] ?? ''));
    $kh   = sanitize_text_field(wp_unslash($_POST['khoa_hoc'] ?? ''));
    if ($ten === '' || mb_strlen($ten) > 100) $go('ten');
    if (!preg_match('/^(\+?84|0)[1-9][0-9]{8}$/', $raw)) $go('sdt');
    $sdt = preg_replace('/^\+?84/', '0', $raw);
    if ($mail !== '' && !is_email($mail)) $go('mail');
    $mail = sanitize_email($mail);
    $okc = array(); foreach (ta_data()['c'] as $c) $okc[] = $c[1];
    if (!in_array($kh, $okc, true)) $kh = '';
    global $wpdb; $t = ta_table();
    // Chống gửi trùng: cùng số điện thoại trong 10 phút thì coi như đã nhận
    $dup = $wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE sdt = %s AND created_at > %s LIMIT 1", $sdt, gmdate('Y-m-d H:i:s', current_time('timestamp') - 600)));
    if (!$dup) {
        $r = $wpdb->insert($t, array('ho_ten' => $ten, 'sdt' => $sdt, 'email' => $mail, 'khoa_hoc' => $kh, 'created_at' => current_time('mysql')));
        if ($r === false) $go('db');
    }
    $go('1');
}
add_action('admin_post_nopriv_ta_register', 'ta_handle_register');
add_action('admin_post_ta_register', 'ta_handle_register');

/* ---------- Trang quản trị: Đăng ký tư vấn ---------- */
add_action('admin_menu', function () {
    add_menu_page('Đăng ký tư vấn', 'Đăng ký tư vấn', 'manage_options', 'ta-registrations', 'ta_admin_page', 'dashicons-id-alt', 26);
});
function ta_admin_page() {
    if (!current_user_can('manage_options')) return;
    global $wpdb; $t = ta_table();
    if (isset($_GET['xoa']) && check_admin_referer('ta_xoa_' . (int)$_GET['xoa'])) {
        $wpdb->delete($t, array('id' => (int)$_GET['xoa']));
        echo '<div class="notice notice-success"><p>Đã xóa.</p></div>';
    }
    $rows = $wpdb->get_results("SELECT * FROM $t ORDER BY id DESC LIMIT 500");
    echo '<div class="wrap"><h1>Đăng ký tư vấn (' . count($rows) . ')</h1>';
    echo '<table class="widefat striped"><thead><tr><th>#</th><th>Họ tên</th><th>Số điện thoại</th><th>Email</th><th>Khóa học</th><th>Thời gian</th><th></th></tr></thead><tbody>';
    if (!$rows) echo '<tr><td colspan="7">Chưa có đăng ký nào.</td></tr>';
    foreach ($rows as $r) {
        $del = wp_nonce_url(admin_url('admin.php?page=ta-registrations&xoa=' . $r->id), 'ta_xoa_' . $r->id);
        echo '<tr><td>' . (int)$r->id . '</td><td>' . esc_html($r->ho_ten) . '</td><td>' . esc_html($r->sdt) . '</td><td>' . esc_html($r->email) . '</td><td>' . esc_html($r->khoa_hoc) . '</td><td>' . esc_html($r->created_at) . '</td><td><a href="' . esc_url($del) . '" onclick="return confirm(\'Xóa đăng ký này?\')">Xóa</a></td></tr>';
    }
    echo '</tbody></table></div>';
}

add_action('wp_enqueue_scripts',function(){wp_enqueue_style('ta',get_stylesheet_uri(),array(),filemtime(get_stylesheet_directory().'/style.css'));});
function ta_u($s=''){return $s?esc_url(add_query_arg('trang',$s,home_url('/'))):esc_url(home_url('/'));}
/* ---------- Ảnh: file thật trong assets/img (jpg/webp/png) -> nếu thiếu thì tạo nền nghệ thuật riêng cho từng vị trí (không lặp) ---------- */
function ta_art($n){
    $h = crc32($n); $hues = array(36,32,40,28,44,34,38,30);
    $h1 = $hues[$h % 8]; $h2 = ($h1 + 42) % 360;
    $x = 760 + ($h % 300); $y = 180 + (($h >> 3) % 260);
    $rings = '';
    foreach (array(60,130,210,300,400) as $i => $r) $rings .= '<circle cx="'.$x.'" cy="'.$y.'" r="'.$r.'" fill="none" stroke="#fff" stroke-opacity="'.(0.20 - $i*0.03).'" stroke-width="2"/>';
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice">'
      .'<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="hsl('.$h1.',72%,20%)"/><stop offset="1" stop-color="hsl('.$h2.',78%,38%)"/></linearGradient>'
      .'<radialGradient id="r"><stop offset="0" stop-color="#D8C39A" stop-opacity=".55"/><stop offset="1" stop-color="#D8C39A" stop-opacity="0"/></radialGradient>'
      .'<pattern id="d" width="28" height="28" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1.6" fill="#fff" fill-opacity=".16"/></pattern></defs>'
      .'<rect width="1200" height="800" fill="url(#g)"/><rect width="1200" height="800" fill="url(#d)"/>'
      .'<circle cx="'.($x-80).'" cy="'.($y+60).'" r="320" fill="url(#r)"/>'.$rings
      .'<circle cx="'.$x.'" cy="'.$y.'" r="22" fill="#B08A55"/>'
      .'<path d="M0 640 C300 560 520 760 820 650 S1100 600 1200 640 V800 H0Z" fill="#fff" fill-opacity=".07"/>';
    if (preg_match('/^t\d/', $n)) $svg .= '<circle cx="600" cy="320" r="112" fill="#fff" fill-opacity=".30"/><path d="M360 800 C380 560 480 500 600 500 S820 560 840 800Z" fill="#fff" fill-opacity=".30"/>';
    return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg . '</svg>');
}
function ta_photos(){return array();}
function ta_img($n){
    $base = preg_replace('/\.[a-z]+$/i', '', $n);
    $opt = get_option('ta_imgs', array());
    if (!empty($opt[$base])) {
        $big = preg_match('/^(hero|cta|results|ph-|.*-banner)/', $base);
        $u = wp_get_attachment_image_url((int)$opt[$base], $big ? 'full' : 'large');
        if ($u) return esc_url($u);
    }
    foreach (array('jpg','jpeg','webp','png') as $ext)
        if (file_exists(get_theme_file_path('assets/img/'.$base.'.'.$ext))) return esc_url(get_theme_file_uri('assets/img/'.$base.'.'.$ext));
    $ph = ta_photos();
    if (isset($ph[$base])) return esc_url($ph[$base]);
    if (file_exists(get_theme_file_path('assets/img/'.$base.'.svg'))) return esc_url(get_theme_file_uri('assets/img/'.$base.'.svg'));
    return ta_art($base);
}
function ta_data(){return array(
'c'=>array(
'luyen-thi-lop-10'=>array('THCS','Luyện thi vào lớp 10','Củng cố kiến thức trọng tâm và luyện đề chuyên sâu.','Học sinh lớp 9','Toán · Ngữ văn · Tiếng Anh','3 tháng','1.500.000đ/tháng','c1.jpg','#B08A55',array('Hệ thống hóa kiến thức nền tảng','Chuyên đề trọng tâm theo cấu trúc đề','Luyện đề chuyên sâu, chấm chữa chi tiết','Thi thử và chiến thuật phân bổ thời gian')),
'luyen-thi-thpt'=>array('THPT','Luyện thi tốt nghiệp THPT','Ôn tập trọng tâm, luyện đề và xây dựng chiến thuật làm bài.','Học sinh lớp 12','Toán · Văn · Anh · KHTN/KHXH','4 tháng','2.000.000đ/tháng','c2.jpg','#B08A55',array('Ôn tập kiến thức cốt lõi','Chuyên đề theo từng môn','Luyện đề minh họa và đề thi thử','Tăng tốc về đích')),
'luyen-thi-dai-hoc'=>array('ĐẠI HỌC','Luyện thi Đại học','Lộ trình chuyên sâu hướng đến mục tiêu điểm số cao.','Học sinh lớp 12','Chuyên đề · Luyện đề · Chiến thuật · Thi thử','6 tháng','2.500.000đ/tháng','c3.jpg','#B08A55',array('Nền tảng và chuyên đề nâng cao','Luyện đề theo cấu trúc mới','Chiến thuật xử lý câu khó','Thi thử toàn diện')),
'luyen-thi-dgnl'=>array('ĐGNL','Luyện thi Đánh giá năng lực','Phát triển tư duy và kỹ năng giải quyết vấn đề.','Học sinh lớp 11–12','Toán học · Ngôn ngữ · Tư duy logic · Giải quyết vấn đề','4 tháng','2.000.000đ/tháng','c4.jpg','#B08A55',array('Tư duy định lượng','Ngôn ngữ và đọc hiểu','Logic và giải quyết vấn đề','Thi thử mô phỏng'))),
't'=>array(array('Nguyễn Minh Anh','Giáo viên Toán','10 năm kinh nghiệm','9.5+ điểm TB học viên','t1.jpg'),array('Trần Hoàng Nam','Giáo viên Vật lý','8 năm kinh nghiệm','9.0+ điểm TB học viên','t2.jpg'),array('Lê Thu Hà','Giáo viên Ngữ văn','9 năm kinh nghiệm','8.5+ điểm TB học viên','t3.jpg'),array('Phạm Quốc Huy','Giáo viên Tiếng Anh','7 năm kinh nghiệm','9.0+ điểm TB học viên','t4.jpg')),
's'=>array(array('Luyện thi lớp 10','luyen-thi-lop-10','10/10/2026','18:30','Offline','Còn chỗ'),array('Luyện thi THPT','luyen-thi-thpt','15/10/2026','19:00','Offline','Còn chỗ'),array('Luyện thi ĐGNL','luyen-thi-dgnl','20/10/2026','18:30','Online','Đang tuyển'),array('Luyện thi Đại học','luyen-thi-dai-hoc','25/10/2026','19:00','Offline','Còn chỗ'))
);}

/* ---------- Chi tiết giáo viên (sửa nội dung tại đây) ---------- */
function ta_gv($name){ return esc_url(add_query_arg(array('trang'=>'giao-vien','gv'=>ta_slug($name)),home_url('/'))); }
function ta_slug($s){
    $s = remove_accents($s); $s = str_replace(array('Đ','đ'), 'd', $s);
    return sanitize_title($s);
}
function ta_teacher_info(){ return array(
 'nguyen-minh-anh' => array(
   'bio' => 'Cô Minh Anh có hơn 10 năm luyện thi Toán cho học sinh lớp 9 và lớp 12. Cô nổi tiếng với cách giảng giải mạch lạc, đi từ bản chất đến kỹ thuật giải nhanh, giúp học sinh vừa hiểu sâu vừa làm bài chắc tay.',
   'edu' => 'Cử nhân Sư phạm Toán – ĐH Sư phạm',
   'courses' => array('Luyện thi vào lớp 10','Luyện thi tốt nghiệp THPT','Luyện thi Đại học'),
   'strengths' => array('Hệ thống hóa kiến thức nền tảng','Chiến thuật xử lý câu vận dụng cao','Chấm chữa bài chi tiết từng em'),
   'ach' => array('Hơn 1.000 học sinh đạt 8+ môn Toán','Nhiều học sinh đạt điểm 9–10 kỳ thi THPT','Biên soạn bộ chuyên đề luyện thi của trung tâm'),
   'quote' => 'Hiểu bản chất thì không có bài toán nào là khó.'),
 'tran-hoang-nam' => array(
   'bio' => 'Thầy Hoàng Nam có 8 năm kinh nghiệm giảng dạy Vật lý. Thầy chú trọng xây dựng tư duy, kết hợp thí nghiệm trực quan và bài tập phân loại theo mức độ để học sinh tiến bộ đều qua từng tuần.',
   'edu' => 'Thạc sĩ Vật lý – ĐH Khoa học Tự nhiên',
   'courses' => array('Luyện thi tốt nghiệp THPT','Luyện thi Đại học','Luyện thi Đánh giá năng lực'),
   'strengths' => array('Tư duy bản chất hiện tượng vật lý','Bài tập phân loại theo mức độ','Mẹo xử lý nhanh câu trắc nghiệm'),
   'ach' => array('Nhiều học sinh đạt 9+ môn Vật lý','Xây dựng lộ trình tăng tốc 3 tháng cho học sinh lớp 12','Giảng viên luyện đề thi thử của trung tâm'),
   'quote' => 'Vật lý là môn học để hiểu thế giới, không phải để học thuộc.'),
 'le-thu-ha' => array(
   'bio' => 'Cô Thu Hà có 9 năm kinh nghiệm luyện thi Ngữ văn. Cô hướng dẫn học sinh cách đọc hiểu, lập dàn ý và triển khai bài nghị luận rõ ràng, giàu cảm xúc, bám sát cấu trúc đề thi mới.',
   'edu' => 'Cử nhân Sư phạm Ngữ văn – ĐH Sư phạm',
   'courses' => array('Luyện thi vào lớp 10','Luyện thi tốt nghiệp THPT'),
   'strengths' => array('Kỹ năng đọc hiểu và lập dàn ý','Nghị luận xã hội và nghị luận văn học','Chữa bài viết cá nhân hóa'),
   'ach' => array('Nhiều học sinh đạt 8.5+ môn Ngữ văn','Xây dựng ngân hàng đề đọc hiểu theo cấu trúc mới','Phụ trách câu lạc bộ viết của trung tâm'),
   'quote' => 'Một bài văn hay bắt đầu từ cách bạn nhìn cuộc sống.'),
 'pham-quoc-huy' => array(
   'bio' => 'Thầy Quốc Huy có 7 năm kinh nghiệm luyện thi Tiếng Anh. Thầy tập trung từ vựng – ngữ pháp trọng tâm, kỹ năng đọc hiểu và chiến thuật phân bổ thời gian để học sinh tự tin bước vào phòng thi.',
   'edu' => 'Cử nhân Ngôn ngữ Anh – ĐH Ngoại ngữ',
   'courses' => array('Luyện thi vào lớp 10','Luyện thi tốt nghiệp THPT','Luyện thi Đánh giá năng lực'),
   'strengths' => array('Ngữ pháp trọng tâm dễ nhớ','Chiến thuật đọc hiểu và điền từ','Luyện đề và phân bổ thời gian'),
   'ach' => array('Nhiều học sinh đạt 9+ môn Tiếng Anh','Thiết kế lộ trình tăng tốc từ vựng 8 tuần','Giảng viên luyện đề thi thử của trung tâm'),
   'quote' => 'Tiếng Anh giỏi nhờ luyện tập đúng cách mỗi ngày.'),
);}

/* ---------- Giao diện > Ảnh giao diện: chọn ảnh thật cho từng vị trí (không cần FTP) ---------- */
function ta_slots(){ return array(
 'Trang chủ' => array('hero'=>'Ảnh nền đầu trang · 1920×1080 (ngang)','home-about'=>'Khối giới thiệu · 1000×1100 (dọc)','results'=>'Nền khối số liệu · 1920×900','cta'=>'Nền kêu gọi đăng ký · 1920×900'),
 'Khóa học' => array('c1'=>'Luyện thi lớp 10 · 800×1000','c2'=>'Luyện thi THPT · 800×1000','c3'=>'Luyện thi Đại học · 800×1000','c4'=>'Luyện thi ĐGNL · 800×1000','c1-banner'=>'Banner chi tiết: lớp 10 · 1920×700','c2-banner'=>'Banner chi tiết: THPT · 1920×700','c3-banner'=>'Banner chi tiết: Đại học · 1920×700','c4-banner'=>'Banner chi tiết: ĐGNL · 1920×700'),
 'Giáo viên (chân dung)' => array('t1'=>'Nguyễn Minh Anh · 600×800','t2'=>'Trần Hoàng Nam · 600×800','t3'=>'Lê Thu Hà · 600×800','t4'=>'Phạm Quốc Huy · 600×800'),
 'Banner các trang' => array('ph-khoahoc'=>'Khóa học','ph-gioithieu'=>'Giới thiệu','ph-giaovien'=>'Giáo viên','ph-lich'=>'Lịch khai giảng','ph-thanhtich'=>'Thành tích','ph-hocphi'=>'Học phí','ph-tintuc'=>'Tin tức','ph-lienhe'=>'Liên hệ'),
 'Khác' => array('about'=>'Trang Giới thiệu · 1000×1100','results-2'=>'Nền trang Thành tích · 1920×900','n1'=>'Tin tức 1 · 800×500','n2'=>'Tin tức 2 · 800×500','n3'=>'Tin tức 3 · 800×500','n4'=>'Tin tức 4 · 800×500'),
);}
add_action('admin_menu', function () { add_theme_page('Ảnh giao diện', 'Ảnh giao diện', 'manage_options', 'ta-images', 'ta_images_page'); });
add_action('admin_enqueue_scripts', function ($h) { if ($h === 'appearance_page_ta-images') wp_enqueue_media(); });
function ta_images_page() {
    if (!current_user_can('manage_options')) return;
    $valid = array(); foreach (ta_slots() as $g) foreach ($g as $k => $l) $valid[$k] = 1;
    if (isset($_POST['ta_imgs_save']) && check_admin_referer('ta_imgs')) {
        $in = isset($_POST['ta_img']) ? (array) wp_unslash($_POST['ta_img']) : array(); $out = array();
        foreach ($in as $k => $id) { $k = sanitize_key($k); $id = absint($id); if (isset($valid[$k]) && $id && wp_attachment_is_image($id)) $out[$k] = $id; }
        update_option('ta_imgs', $out); echo '<div class="notice notice-success"><p>Đã lưu ảnh.</p></div>';
    }
    $opt = get_option('ta_imgs', array());
    echo '<div class="wrap"><h1>Ảnh giao diện</h1><p>Mỗi vị trí dùng <b>một ảnh riêng</b>. Bấm “Chọn ảnh” để tải lên hoặc chọn từ thư viện. Vị trí để trống sẽ hiển thị nền thiết kế mặc định. Nên dùng ảnh JPG/WebP dưới 300KB.</p><form method="post">';
    wp_nonce_field('ta_imgs');
    foreach (ta_slots() as $g => $items) {
        echo '<h2 style="margin-top:28px">' . esc_html($g) . '</h2><div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:14px">';
        foreach ($items as $k => $l) {
            $id = isset($opt[$k]) ? (int) $opt[$k] : 0; $u = $id ? wp_get_attachment_image_url($id, 'medium') : '';
            echo '<div class="ta-row" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px"><div style="height:130px;background:#f0f0f1;border-radius:6px;overflow:hidden;margin-bottom:8px"><img src="' . esc_url($u) . '" alt="" style="width:100%;height:100%;object-fit:cover;' . ($u ? '' : 'display:none') . '"></div>'
              . '<b>' . esc_html($k) . '</b><div style="color:#646970;font-size:12px;margin:2px 0 8px">' . esc_html($l) . '</div>'
              . '<input type="hidden" name="ta_img[' . esc_attr($k) . ']" value="' . $id . '"><button class="button ta-pick">Chọn ảnh</button> <button class="button-link-delete ta-clr" style="margin-left:6px">Xóa</button></div>';
        }
        echo '</div>';
    }
    echo '<p style="margin-top:24px"><button class="button button-primary button-hero" name="ta_imgs_save" value="1">Lưu thay đổi</button></p></form></div>';
    echo '<script>jQuery(function($){$(".ta-pick").on("click",function(e){e.preventDefault();var r=$(this).closest(".ta-row"),f=wp.media({title:"Chọn ảnh",multiple:false,library:{type:"image"}});f.on("select",function(){var a=f.state().get("selection").first().toJSON();r.find("input").val(a.id);r.find("img").attr("src",a.sizes&&a.sizes.medium?a.sizes.medium.url:a.url).show()});f.open()});$(".ta-clr").on("click",function(e){e.preventDefault();var r=$(this).closest(".ta-row");r.find("input").val("");r.find("img").hide()})});</script>';
}

/* ---------- Nút liên hệ nổi (Zalo · Gọi · Bản đồ · Lịch khai giảng) ----------
   Chỉ thêm nút, không đụng tới giao diện hiện có. Sửa số điện thoại / Zalo / địa chỉ tại đây. */
/* Thông tin liên hệ DEMO - sửa 1 chỗ này, toàn site tự cập nhật */
function ta_contact() {
    return array(
        'phone'         => '0905123456',
        'phone_display' => '0905 123 456',
        'zalo'          => '0905123456',
        'address'       => '45 Thống Nhất, P. Lộc Thọ, TP. Nha Trang, Khánh Hòa',
        'address_short' => '45 Thống Nhất, Nha Trang',
        'email'         => 'contact@targetacademy.vn',
    );
}

function ta_float_buttons() {
    $c       = ta_contact();
    $phone   = $c['phone'];
    $zalo    = $c['zalo'];
    $address = $c['address'];
    $map     = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode('Trung tâm luyện thi Target Academy, ' . $address);
    ?>
<div class="ta-fab" id="taFab" aria-label="Liên hệ nhanh">
    <div class="ta-fab-list" id="taFabList">
        <a class="ta-fab-b ta-zalo" href="<?php echo esc_url('https://zalo.me/' . $zalo); ?>" target="_blank" rel="noopener" aria-label="Chat Zalo" data-tip="Zalo: <?php echo esc_attr($c['phone_display']); ?>"><span>Zalo</span></a>
        <a class="ta-fab-b ta-tel" href="<?php echo esc_url('tel:' . $phone); ?>" aria-label="Gọi điện" data-tip="Gọi: <?php echo esc_attr($c['phone_display']); ?>"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.6a1 1 0 0 1-.25 1z"/></svg></a>
        <a class="ta-fab-b ta-map" href="<?php echo esc_url($map); ?>" target="_blank" rel="noopener" aria-label="Chỉ đường" data-tip="<?php echo esc_attr($c['address_short']); ?>"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg></a>
        <a class="ta-fab-b ta-cal" href="<?php echo ta_u('lich-khai-giang'); ?>" aria-label="Lịch khai giảng" data-tip="Lịch khai giảng"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 2a1 1 0 0 1 1 1v1h8V3a1 1 0 1 1 2 0v1h1a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1V3a1 1 0 0 1 1-1zm12 8H5v10h14V10zM8 12h3v3H8v-3z"/></svg></a>
    </div>
    <button type="button" class="ta-fab-t" id="taFabToggle" aria-expanded="true" aria-controls="taFabList" aria-label="Thu gọn / mở rộng nút liên hệ"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></button>
</div>
<script>(function(){var f=document.getElementById('taFab'),t=document.getElementById('taFabToggle');if(!f||!t)return;t.addEventListener('click',function(){var c=f.classList.toggle('is-closed');t.setAttribute('aria-expanded',c?'false':'true')})})();</script>
    <?php
}
add_action('wp_footer', 'ta_float_buttons', 5);
