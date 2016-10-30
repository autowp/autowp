create database autowp_test default character set utf8;
GRANT ALL PRIVILEGES ON autowp_test.* TO autowp_test@localhost IDENTIFIED BY "test";
flush privileges;
\. module/Application/test/ApplicationTest/_files/dump.sql