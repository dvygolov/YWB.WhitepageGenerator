<?php
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );

/*
 *
 * SCRIPT SETTINGS
 *
 * */
$langFrom = 'ru';                                       // SOURCE LANGUAGE
$langTo = 'hr';                                         // TRANSLATION LANGUAGE
$site = 'https://www.myjane.ru/articles/rubric/?id=7';  // LINK TO ARTICLES RUBRIC ON MYJANE.RU WEBSITE
$startPage = 1;                                         // PAGE NUMBER FROM WICH THE PARSING WILL START
$depth = 20;                                            // HOW MANY SITE'S PAGES SHOULD WE PROCESS?
$articlesCount = 5;                                    // HOW MANY ARTICLES WILL BE IN OUR WHITE PAGE?
$withImages = 1;                                        // SHOULD WE ADD RANDOM PICTURES FROM PIXABAY?

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/google.php';
require_once __DIR__ . '/htmldom.php';

$tr = new GoogleTranslate();

$articles = [];

echo "Getting article links...\n";

for ( $page = $startPage; $page <= $startPage + $depth; $page++ ) {
    echo "Parsing page #$page\n";

    $html = file_get_html( "$site&page=$page" );

    foreach ( $html->find( 'a' ) as $a ) {
        if ( strpos( $a->href, 'articles/text/?id=' ) !== false ) {
            if ( strpos( $a->href, 'comments' ) === false ) {
                $articles[] = $a->href;
            }
        }
    }
}


// delete old white pages from the output folder
$dir = __DIR__ . "/output/";
$allowedFiles = [ '.', '..', 'index.php', 'contact.php', 'themes' ];
$files = scandir( $dir );
foreach ( $files as $file ) {
    if ( !in_array( $file, $allowedFiles ) ) unlink( $dir . $file );
}

for ( $i = 1; $i <= $articlesCount; $i++ ) {
    /*
     *
     * Article parsing
     *
     * */
    echo "Processing article #$i\n";

    $a = array_rand( $articles );
    $article = $articles[ $a ];
    unset( $articles[ $a ] );
    $translated = [];

    $html = file_get_html( $article );
    $data[ 'title' ] = $tr->translate( $html->find( 'h1', 0 )->plaintext,$langFrom,$langTo );
    $data[ 'text' ] = $html->find( 'div.usertext > div.usertext', 0 )->outertext;

    /*
     *
     * PROCESSING ARTICLE TEXT
     *
     * */
    $html = str_get_html( $data[ 'text' ] );

    // remove all links
    foreach ( $html->find( 'a' ) as $a ) {
        $a->href = '#';
    }

    // remove banners
    foreach ( $html->find( 'div > div' ) as $div ) {
        $div->innertext = '';
    }

    $html = $html->save();

    // search and writedown article preview
    preg_match( '/<b>.*<\/b>/', $html, $matches );
    if ( $matches ) {
        $data[ 'short' ] = str_replace( '<b>', '', str_replace( '</b>', '', $matches[ 0 ] ) ) . '...';
        $data[ 'short' ] = $tr->translate( $data[ 'short' ],$langFrom,$langTo );
    }

    // HTML processing
    $html = preg_replace( '/<a[^>]+>/', '', $html );
    $html = preg_replace( '/<\/a>/', '', $html );
    $html = preg_replace( '/<br \/> <br \/>/', '<br />', $html );
    $html = preg_replace( '/<br \/>/', '<p>', $html );
    $html = preg_replace( '/<br>/', '<p>', $html );
    $html = preg_replace( '/<b>/', '<p><b>', $html );
    $html = preg_replace( '/<\/b>/', '</b></p>', $html );
    $html = preg_replace( '/<div class\=\"usertext\">/', '', $html );
    $html = preg_replace( '/<div>/', '', $html );
    $html = preg_replace( '/<\/div>/', '', $html );
    $html = preg_replace( '/<h3><p>/', '<h3>', $html );

    // article translation
    $parts = preg_split( '/(<[^>]*[^\/]>)/i', $html, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );
    foreach ( $parts as $p ) {
        if ( $langFrom != $langTo ) 
            usleep( 500000 );
        if ( empty( trim( $p ) ) ) continue;
        if ( strpos( $p, '<' ) !== false ) {
            $translated[] = $p;
            continue;
        }
        $p = trim( $p );
        if ( mb_strlen( $p ) < 5000 ) {
            $translated[] = $tr->translate( $p,$langFrom,$langTo );
        }
    }
    $html = implode( '', $translated );

    // saving article
    $posts[] = [ 'id' => $i, 'title' => $data[ 'title' ], 'filename' => "$i.html", 'short' => $data[ 'short' ] ?? '' ];
    file_put_contents( "$dir/$i.html", $html );
    file_put_contents( "$dir/posts.json", json_encode( $posts ) );
}

/*
 *
 * Loading images
 *
 * */
if ( $withImages ) {
    foreach ( $posts as $post ) {

        echo "Loading image #{$post['id']}\n";

        $imageFile = "$dir/{$post['id']}.jpg";
        $image = getImage();

        file_put_contents( $imageFile, file_get_contents( $image ) );
    }
}

echo "Final processing...\n";

/*
 *
 * Website Text Elements Translation
 *
 * */
$lang = [
    'readMore'     => $tr->translate( 'Читать полностью...',"ru",$langTo ),
    'search'       => $tr->translate( 'Поиск',"ru",$langTo ),
    'searchInput'  => $tr->translate( 'Хочу найти...',"ru",$langTo ),
    'recentPosts'  => $tr->translate( 'Свежие статьи',"ru",$langTo ),
    'published'    => $tr->translate( 'Опубликовано',"ru",$langTo ),
    'prev'         => $tr->translate( 'Назад',"ru",$langTo ),
    'next'         => $tr->translate( 'Вперед',"ru",$langTo ),
    'blog'         => $tr->translate( 'Блог',"ru",$langTo ),
    'contact'      => $tr->translate( 'Контакты',"ru",$langTo ),
    'name'         => $tr->translate( 'Ваше имя',"ru",$langTo ),
    'message'      => $tr->translate( 'Сообщение',"ru",$langTo ),
    'send'         => $tr->translate( 'Отправить сообщение',"ru",$langTo ),
    'searchSubmit' => $tr->translate( 'Найти!',"ru",$langTo ),
    'images'       => $tr->translate( 'Галерея',"ru",$langTo ),
    'success'      => $tr->translate( 'Спасибо за ваше сообщение!',"ru",$langTo ),
];
file_put_contents( "$dir/lang.json", json_encode( $lang ) );

/*
 *
 * Configuration file processing
 *
 * */
$themes = [ 'Cerulean', 'Cosmo', 'Cyborg', 'Darkly', 'Flatly', 'Journal', 'Litera', 'Lumen', 'Lux', 'Materia', 'Minty', 'Pulse', 'Sandstone', 'Simplex', 'Slate', 'Solar', 'Spacelab', 'Superhero', 'United', 'Yeti' ];
$theme = strtolower( $themes[ rand( 0, count( $themes ) - 1 ) ] );
$config = [
    'theme' => $theme,
];
file_put_contents( "$dir/config.json", json_encode( $config ) );
echo "ALL DONE!";

function getImage () {
    usleep( 1000000 );
    $result = @file_get_contents( 'https://pixabay.com/api/?id=' . rand( 1, 999999 ) . '&key=7331766-71ba439a87eec21d8ee411b77' );
    if ( strpos( $result, 'ERROR 400' ) !== false || !$result ) {
        return getImage();
    } else {
        $result = json_decode( $result, true );

        return $result[ 'hits' ][ 0 ][ 'largeImageURL' ];
    }
}
?>