<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BookApi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:bookapi {title}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '国会図書館のAPIを叩いてデータを取得する';

    protected $title = "";

    protected $query_min = 2;  //検索文字列の最短
    protected $query_max = 40; //検索文字列の最長


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->title = $this->argument('title');

        // メイン・プログラム =======================================================
        $errmsg = '';
        $query = self::getParam_validateStr('query', 'auto', '', self::query_min, self::query_max);
        if ($query == FALSE) $errmsg = 'error > 書籍名は' . self::query_min . '文字以上，' . self::uery_max . '文字以下で入力してください．';
        if (self::getParam('reset', FALSE, '') != '') $query = '';

        $items = array();

        $url = self::getURL_searchNDL($query);

        $res = "";

        if ($query != '') {
            $res = self::searchNDL($items, $query);
        }
    }

// サブルーチン ==============================================================
/**
 * エラー処理ハンドラ
 */
function myErrorHandler($errno, $errmsg, $filename, $linenum, $vars)
{
    echo 'Sory, system error occured !';
    exit(1);
}



/**
 * 指定したパラメータを取り出す
 * @param string $key パラメータ名（省略不可）
 * @param bool $auto TRUE＝自動コード変換あり／FALSE＝なし（省略時：TRUE）
 * @param mixed $def 初期値（省略時：空文字）
 * @return    string パラメータ／NULL＝パラメータ無し
 */
function getParam($key, $auto = TRUE, $def = '')
{
    if (isset($_GET[$key])) $param = $_GET[$key];
    else if (isset($_POST[$key])) $param = $_POST[$key];
    else                            $param = $def;
    if ($auto) $param = mb_convert_encoding($param, INTERNAL_ENCODING, 'auto');
    return $param;
}

/**
 * 指定したパラメータを取り出す（整数バリデーション付き）
 * @param string $key パラメータ名（省略不可）
 * @param int $def デフォルト値（省略可）
 * @param int $min 最小値（省略可）
 * @param int $max 最大値（省略可）
 * @return    int 値／FALSE
 */
function getParam_validateInt($key, $def = '', $min = 0, $max = 9999)
{
    //パラメータの存在チェック
    if (isset($_GET[$key])) $param = $_GET[$key];
    else if (isset($_POST[$key])) $param = $_POST[$key];
    else                            $param = $def;
    //整数チェック
    if (preg_match('/^[0-9\-]+$/', $param) == 0) return FALSE;
    //最小値・最大値チェック
    if ($param < $min || $param > $max) return FALSE;

    return $param;
}

/**
 * 指定したパラメータを取り出す（文字列バリデーション付き）
 * @param string $key パラメータ名（省略不可）
 * @param bool $auto TRUE＝自動コード変換あり／FALSE＝なし（省略時：TRUE）
 * @param int $def デフォルト値（省略可）
 * @param int $min 文字列長・最短（省略可）
 * @param int $max 文字列長・最長（省略可）
 * @return    string 文字列／FALSE
 */
public function getParam_validateStr($key, $auto = TRUE, $def = '', $min = 3, $max = 80)
{
    $param = $this->title;

    if ($auto) $param = mb_convert_encoding($param, INTERNAL_ENCODING, 'auto');
    $param = htmlspecialchars(strip_tags($param));        //タグを除く
    //文字列長チェック
    $len = mb_strlen($param);
    if ($len < $min || $len > $max) return FALSE;

    return $param;
}

/**
 * 指定XMLファイルを読み込んでDOMを返す
 * @param string $xml XMLファイル名
 * @return    object DOMオブジェクト／NULL 失敗
 */
function read_xml($xml)
{
    if (($fp = fopen($xml, 'r')) == FALSE) return NULL;

    //いったん変数に読み込む
    $str = fgets($fp);
    $str = preg_replace('/UTF-8/', 'utf-8', $str);

    while (!feof($fp)) {
        $str = $str . fgets($fp);
    }
    fclose($fp);

    //DOMを返す
    $dom = new \DOMDocument();
    $dom->loadXml($str);
    if ($dom == NULL) {
        echo "\n>Error while parsing the document - " . $xml . "\n";
        exit(1);
    }

    return $dom;
}

/**
 * チェックデジットの計算（モジュラス11 ウェイト10-2）ASIN用
 * @param string $code 計算するコード（最下位桁がチェックデジット）
 * @return    int チェックデジット
 */
function cd11($code)
{
    $cd = 0;
    for ($pos = 10; $pos >= 2; $pos--) {
        $n = substr($code, (10 - $pos), 1);
        $cd += $n * $pos;
    }
    $cd = $cd % 11;
    $cd = 11 - $cd;
    if ($cd == 10) $cd = 'X';
    if ($cd == 11) $cd = '0';
    return $cd;
}

/**
 * チェックデジットの計算（モジュラス10 ウェイト3）
 * @param string $code 計算するコード（最下位桁がチェックデジット）
 * @return    int チェックデジット
 */
function cd10($code)
{
    $cd = 0;
    for ($pos = 13; $pos >= 2; $pos--) {
        $n = substr($code, (13 - $pos), 1);
        $cd += $n * (($pos % 2) == 0 ? 3 : 1);
    }
    $cd = $cd % 10;
    return ($cd == 0) ? 0 : 10 - $cd;
}

/**
 * ISBNコードをASINコードに変換する
 * @param string $isbn ISBNコード（10進数10桁 or 13桁）
 * @return    string ASINコード（10進数10桁）／FALSE：変換に失敗
 */
function isbn2asin($isbn)
{
    //旧ISBNコードの場合はそのまま返す
    if (preg_match('/^[0-9]{10}$/', $isbn) == 1) {
        if (self::cd11($isbn) != substr($isbn, 9, 1)) return FALSE;
        return $isbn;
    }

    //入力値チェック
    if (preg_match('/^[0-9]{13}$/', $isbn) != 1) return FALSE;
    if (self::cd10($isbn) != substr($isbn, 12, 1)) return FALSE;
    if (preg_match('/^978/', $isbn) == 0) return FALSE;

    $code = substr($isbn, 3, 10);        //10-1桁目を取り出す
    $cd = self::cd11($code);

    return substr($isbn, 3, 9) . $cd;
}

/**
 * 国立国会図書館サーチAPI のURLを取得する
 * https://iss.ndl.go.jp/information/wp-content/uploads/2018/09/ndlsearch_api_20180925_jp.pdf
 * idxというパラメーターでページネーションもできそう
 *
 * @param string $query タイトル（UTF-8；部分一致）
 *                         またはISBN（10桁または13桁；完全一致または前方一致）
 *                         【省略不可】
 * @param string $creater 作成者（UTF-8；部分一致）
 * @param string $from 開始出版年月日（YYYY-MM-DD）
 * @param string $until 終了出版年月日（YYYY-MM-DD）
 * @param int $cnt 出力レコード上限値（省略時=20）
 * @param string $mediatype 資料種別（省略時=1:本)
 * @return    string WebAPI URL
 */
function getURL_searchNDL($query, $creater = '', $from = '', $until = '', $cnt = 20, $mediatype = '1')
{
    $isbn = '';
    if (preg_match('/^[0-9|\-]+$/', $query) == 1) {
        $title = '';
        $isbn = 'isbn=' . preg_replace('/\-/', '', $query);
    }
    if ($isbn == '') {
        $title = 'title=' . urlencode($query);
    }

    $creater = ($creater != '') ? '&creater=' . urlencode($creater) : '';
    $from = ($from != '') ? '&from=' . $from : '';
    $until = ($until != '') ? '&until=' . $until : '';
    $cnt = ($cnt > 0) ? '&cnt=' . $cnt : '';
    $mediatype = ($mediatype != '') ? '&mediatype=' . $mediatype : '';

    return 'http://iss.ndl.go.jp/api/opensearch?' . $title . $isbn . $creater . $from . $until . $cnt . $mediatype;
}

/**
 * 国立国会図書館サーチAPIを呼び出し結果を配列に格納する
 * @param array $items 書籍情報格納用
 * @param string $query タイトル（UTF-8；部分一致）
 *                         またはISBN（10桁または13桁；完全一致または前方一致）
 *                         【省略不可】
 * @param string $creater 作成者（UTF-8；部分一致）
 * @param string $from 開始出版年月日（YYYY-MM-DD）
 * @param string $until 終了出版年月日（YYYY-MM-DD）
 * @param int $cnt 出力レコード上限値（省略時=50）
 * @param string $mediatype 資料種別（省略時=1:本)
 * @return    bool TRUE/FALSE
 */
function searchNDL(&$items, $query, $creater = '', $from = '', $until = '', $cnt = 20, $mediatype = '1')
{
    //名前空間
    define('NS_DCMITYPE', 'http://purl.org/dc/dcmitype/');
    define('NS_RDFS', 'http://www.w3.org/2000/01/rdf-schema#');
    define('NS_XSI', 'http://www.w3.org/2001/XMLSchema-instance');
    define('NS_OPENSEARCH', 'http://a9.com/-/spec/opensearchrss/1.0/');
    define('NS_DC', 'http://purl.org/dc/elements/1.1/');
    define('NS_DCNDL', 'http://ndl.go.jp/dcndl/terms/');
    define('NS_RDF', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
    define('NS_RCTERMS', 'http://purl.org/dc/terms/');

    $url = self::getURL_searchNDL($query, $creater, $from, $until, $cnt, $mediatype);

    //PHP5用; SimpleXML利用
    $xml = simplexml_load_file($url);
    //レスポンス・チェック
    $count = @count($xml->channel->item);
    if ($count <= 0) return FALSE;
    $i = 0;
    foreach ($xml->channel->item as $item) {
        $node = $item->children(NS_DC);
        $isbn = '';
        foreach ($node->identifier as $id) {
            if (preg_match('/ISBN/iu', (string)$id->attributes(NS_XSI)) == 1) $isbn = (string)$id;
        }
        if ($isbn == '') continue;
        $items[$isbn]['title'] = (string)$node->title;
        $items[$isbn]['author'] = (string)$node->creator;
        $items[$isbn]['publisher'] = (string)$node->publisher;
        $items[$isbn]['NDC'] = '';
        foreach ($node->subject as $id) {
            if (preg_match('/NDC/iu', (string)$id->attributes(NS_XSI)) == 1) $items[$isbn]['NDC'] = (string)$id;
        }
        $items[$isbn]['url'] = (string)$item->link;
        $items[$isbn]['pubDate'] = (string)$item->pubDate;
        $node = $item->children(NS_DCNDL);
        if ($node->volume != '') {
            $items[$isbn]['title'] = $items[$isbn]['title'] . '（' . (string)$node->volume . '）';
        }
        $i++;

    }

    return ($i > 0) ? TRUE : FALSE;
}


/*
** バージョンアップ履歴 ===================================================
 *
 * @version  1.1  2017/04/08  PHP7 対応
 * @version  1.0  2014/08/10
*/

}
