<?php

// 使い方
// $FREEM_ID と $PASSWORD にふりーむID とパスワードを入れる
// 適当な PHP 実行環境で実行する。
// この PHP ファイルのあるディレクトリに csv ディレクトリがつくられてDL情報がCSVとして保存される
// 何度実行しても大丈夫。
// ふりーむのダウンロード情報保存期間が7日なので、週1回実行すればOK。

/////////////////////////////////////////////////////////
// ふりーむのログインID とパスワードを入れる
/////////////////////////////////////////////////////////
$FREEM_ID = 'test@exsample.com';
$PASSWORD = 'enter_password';

// Cookie情報を保存する一時ファイルディレクトリにファイルを作成します
$tmp_path =  tempnam(sys_get_temp_dir(), "FRM");

// ---------------------------------
// ログイン処理
// ---------------------------------

//postするデータの配列
$login_post_data = array(
    'data[User][email_pc]' => $FREEM_ID,
    'data[User][password]'=> $PASSWORD,
    'data[User][remember_me]' => 1,
    'ref' => 'https://www.freem.ne.jp/',
);

$login_url = "https://www.freem.ne.jp/account/login";
$user_agent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/64.0.3282.167 Safari/537.36";

$curl = curl_init();

//オプション
curl_setopt($curl, CURLOPT_URL, $login_url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_USERAGENT, $user_agent);
// curl_setopt($curl, CURLOPT_VERBOSE, true);

//POST送信
curl_setopt($curl,CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($login_post_data));

//Cookie受信
//cookieオプション
curl_setopt($curl, CURLOPT_COOKIEFILE, $tmp_path);
curl_setopt($curl, CURLOPT_COOKIEJAR, $tmp_path);
curl_exec($curl);
curl_close($curl);

// ---------------------------------
// ダウンロード数のスクレピング処理
// ---------------------------------

$analytics_url = "https://www.freem.ne.jp/creator/analytics";

$curl = curl_init();

curl_setopt($curl, CURLOPT_URL, $analytics_url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_USERAGENT, $user_agent);
//curl_setopt($curl, CURLOPT_VERBOSE, true);

//Cookie送信
curl_setopt($curl, CURLOPT_COOKIEFILE, $tmp_path);
curl_setopt($curl, CURLOPT_COOKIEJAR, $tmp_path);

$html = curl_exec($curl);
curl_close($curl);

unlink($tmp_path);

// script 部に埋め込まれている google chart の元データを抜き出す
$pattern = '/arrayToDataTable\((.*?)\);/s';
if (1 !== preg_match($pattern, $html, $match)) {
    // デコードエラー
    print 'ページのスクレイピングに失敗しました。ページのHTMLを確認して正規表現を修正してください。';
    exit(1);
}

$dl_data = json_decode($match[1], true);
//var_dump($dl_data);

if (null === $dl_data) {
    // デコードエラー
    print 'json のデコードに失敗しました。スクレイピング元データ形式が変更された可能性があります。ページのHTMLを確認して正規表現を修正してください。';
    exit(1);
}

// ---------------------------------
// CSV 保存処理
// 以前にCSV に保存したデータをマージして保存する
// 何度実行しても問題ない。
// ---------------------------------

// CSV に保存する
$csv_file_dir = path_combine(dirname(__FILE__), 'csv');
if (!file_exists($csv_file_dir)) {
    if (!mkdir($csv_file_dir)) {
        print 'ディレクトリの作成に失敗しました。書き込み権限があるか確認してださい。' . $csv_file_dir . PHP_EOL;
        exit(1);
    }
}
$csv_filename = path_combine($csv_file_dir, 'download.csv');
if (!file_exists($csv_filename)) {
    if (!touch($csv_filename)) {
        print 'CSV ファイルの作成に失敗しました。書き込み権限があるか確認してださい。' . $csv_filename . PHP_EOL;
        exit(1);
    }
}

// 既存の CSV 読み出し
$file = new SplFileObject($csv_filename);
$file->setFlags(SplFileObject::READ_CSV);
$raw_records = array();
foreach ($file as $row) {
    // 空行を読み込むと
    // array(1) {
    //     [0] =>
    //     NULL
    // }
    // が返ってくる...
    if (count($row) === 1 && null === $row[0]) {
        continue;
    }
    if (!empty($row)) {
        $raw_records[] = $row;
    }
}
//print 'csv せいり読み込み' . PHP_EOL; var_dump($raw_records);

$csv_header = null;
$csv_column_index = array();
if (empty($raw_records)) {
    // 初めて実行したか、初期化された
    $csv_header = $dl_data[0];
} else {
    $csv_header = $raw_records[0];
}

// カラムをキーにデータを整理
$csv_records = array();
for ($i = 1; $i < count($raw_records); $i++) {
    $a_record = $raw_records[$i];
    $csv_records[$a_record[0]] = array();
    for ($j = 1; $j < count($a_record); $j++) {
        $csv_records[$a_record[0]][$csv_header[$j]] = $a_record[$j];
    }
}

//var_dump($csv_header);
//print 'csv 整理' . PHP_EOL; var_dump($csv_records);

// スクレイピングしデータもカラムをキーに配列を初期化
$dl_data_header = $dl_data[0];
// カラム名をキーに配列を初期化
$dl_reordes = array();
for ($i = 1; $i < count($dl_data); $i++) {
    $a_record = $dl_data[$i];
    $csv_records[$a_record[0]] = array();
    for ($j = 1; $j < count($a_record); $j++) {
        $dl_reordes[$a_record[0]][$dl_data_header[$j]] = $a_record[$j];
    }
}

// var_dump($dl_data_header);
//print 'DL データ' . PHP_EOL; var_dump($dl_reordes);

$marged_csv = array_merge_recursive($dl_reordes, $csv_records);
$all_products = array();
foreach ($marged_csv as $product_dl_vals) {
    foreach (array_keys($product_dl_vals) as $product_name) {
        $all_products[$product_name] = null;
    }
}

//var_dump($all_products);
//print 'マージ データ' . PHP_EOL; var_dump($marged_csv);

//一時ファイル削除
$output_records = array();
$date_list = array_keys($marged_csv);
sort($date_list);
foreach ($date_list as $date) {
    $product_dl_vals = $marged_csv[$date];
    $dl_record = array($date);
    foreach (array_keys($all_products) as $product_name) {
        if (array_key_exists($product_name, $product_dl_vals)) {
            $dl_record[] = $product_dl_vals[$product_name];
        } else {
            $dl_record[] = 0;
        }
    }
    $output_records[] = $dl_record;
}

// 怖いのでバックアップ；；
copy($csv_filename, $csv_filename . '.bak_' . date("YmdHis"));
$file = new SplFileObject($csv_filename, 'w');
// ヘッダー書きだし
$file->fputcsv(array_merge(array($csv_header[0]), array_keys($all_products)));
// 行書きだし
foreach ($output_records as $record) {
    $file->fputcsv($record);
}

/**
 * 安全なパス文字列の連結
 * @param string $dir
 * @param string $file
 * @return string
 */
function path_combine($dir, $file)
{
    return rtrim($dir, '\\/') . DIRECTORY_SEPARATOR . $file;
}