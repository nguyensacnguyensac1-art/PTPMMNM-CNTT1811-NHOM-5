<?php
// Mở http://localhost/releasehub/ -> chuyển tới thư mục public/ (redirect tương đối, không lộ đường dẫn máy).
header('Location: public/', true, 302);
exit;
