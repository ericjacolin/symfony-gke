; Errors
display_errors = {{ .Env.PHP_DISPLAY_ERRORS }}
display_startup_errors = On
error_reporting = {{ .Env.PHP_ERROR_REPORTING }}
log_errors = On

; Session
session.gc_maxlifetime = 7200

; File uploads
upload_max_filesize = 16M
post_max_size = 16M

[mail function]
sendmail_path = "/usr/bin/msmtp -t -v"
