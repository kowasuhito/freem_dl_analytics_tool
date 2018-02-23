# Why
ふりーむ！に登録した作品のダウンロード数を確認したい場合、ふりーむ！にログインしてダウンロード解析で確認する必要がある。
ただし、ふりーむ！のダウンロード解析は7日間しか表示されないので後から確認しようとしたときに確認できない。
ダウンロード数を自動で取得して保存する補助ツールがほしい。

# requirements

* PHP5.6  
※実行するために PHP が必要ですので事前にインストールしてください

# usage

1. ふりーむ！のアカウントID / パスワードを設定する
https://github.com/kowasuhito/freem_dl_analytics_tool/blob/master/main.php#L13-L14
1. 実行する  
  ・ e.g. windows の場合  
  `C:\>c:\pleiades\xampp\php\php.exe workspace\freem_dl_analytics_tool\main.php`
1. main.php と同じ場所に csv というディレクトリが作られ、download.csv が生成されています。
1. 翌日に実行すれば、download.csv に追加された分のダウンロード数が追記されます。　
