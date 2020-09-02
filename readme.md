## 安裝方式
- 準備.env檔案
```
cp .env.example .env
```
- 修改.env裡面的設定
- 安裝php必要的library、設定laravel的access key
```
composer install
php artisan key:generate
```

## 資料庫設定
- 資料庫名稱參考.env設定
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=test
DB_USERNAME=allensu
DB_PASSWORD=
```
- 建立資料表
```
php artisan migrate
```

## 啟動網站
```
php artisan serve // default port is 8000
或是指定port號
php artisan serve --port=8001 
```


## 啟動卡機服務
- 啟動 TcpSocket Server
```
php artisan soyal:soyal-message-server start
```

## 排程啟動
- Async Process
```
php artisan queue:work
```

## 設定排程 (ubuntu's crontab)
- crontab -e 打開排程編輯檔案
```
// 新加上一行指令, 指令代表 每分鐘執行一次
* * * * * php /var/www/html/soyalmiddleware/artisan schedule:run >> /dev/null 2>&1
```
- 要執行的 Laravel Command 在 Console/Kernel.php 檔做設定
```
protected function schedule(Schedule $schedule)
{
    $schedule->command('soyal:daily-devices-update-pin-code')->everyMinute();
    ...
    ...
}
```
