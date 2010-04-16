<?php
/*---------------------------------------------------------------------------------
vBulletin2RDF.php  script to map a vBulletin forum archive to RDF
-----------------------------------------------------------------------------------

 4/16/10  ready for googlecode
 2/19/10  extract text from sjwinfo.org; sent to Joanne and Matthias
 2/15/10  started

This script extracts text from the archive view of vBulletin boards, started
according to the recipe sent from Matthias Samwald and Joanne Luciano.
We write out an RDF version of the captured information.
The RDF uses the SIOC vocabulary.

Matthias suggests that one can start with the following bulletin boards:
http://www.sjwinfo.org/forum/archive/index.php
http://www.nomorepanic.co.uk/archive/index.php
http://mdhealthforum.com/archive/index.php

You first need to wget the archive.
To wget 10 MBytes:
wget -m --no-parent --directory-prefix=test http://www.sjwinfo.org/forum/archive/ --quota=10m
wget -m --no-parent --directory-prefix=test http://www.nomorepanic.co.uk/archive/ --quota=10m

But mdhealthforum.com robots.txt disallows everything.
wget -m --no-parent --directory-previx=test http://mdhealthforum.com/archive/     --quota=10m

You will then have a set of t-*.html files, e.g.,
test/www.sjwinfo.org/forum/archive/index.php/t-*.html, among other files.
Each of the t-*.html files is the vBulletin archive for a single thread.

To run the present script:
php -f vBulletin2RDF.php directory [keywords] > output.rdf

For example:
php -f vBulletin2RDF.php test/www.sjwinfo.org/forum/archive/index.php/
php -f vBulletin2RDF.php test/www.sjwinfo.org/forum/archive/index.php/ depression
php -f vBulletin2RDF.php test/www.sjwinfo.org/forum/archive/index.php/ depression "side effects"
(Note that www.sjwinfo.org t-2549.html contains one post by "angelgia10" that
appears to be spam and that breaks the present script.
Just delete t-2549.html.)

If there are no keywords then we output all the threads, posts and content.
If there are keywords then we output only those threads, posts and content where
one or more of the keywords appears in the content.
We use preg_match() to compare keywords independently of case.
The comparison is not sophisticated, so a multi-word keyword encased in quotes
probably will not pass if there is any deviation in the whitespace or newline
composition in the original content.

The output RDF uses the SIOC vocabulary, whose rough hierarchy is:
    sioc:Site
    sioc:Forum
    sioc:Thread
    sioc:Post
    sioc:Content
    
There is one sioc:Site and one sioc:Forum.
There are several sioc:Threads, each of which can have several sioc:Posts.
Each sioc:Post has one sioc:Content.
doOneFile() below walks through the required states.

All the text from the archive is converted to UTF-8 with utf8_encode().

Upgrades
--------
A possible upgrade would deposit some vocabulary in the RDF that describes the
keywords if they are chosen.
While one might use skos:note here, it does not seem like a proper fit.
Suggestions are welcome.

The workflow of the present script could be combined into a wget-like crawler.

DERI has been working on producing SIOC directly from vBulletin boards.
Google 'sioc vbulletin'.
http://www.johnbreslin.com/blog/2008/02/15/a-funny-thing-happened-on-the-way-to-the-forum-article-in-indo-about-10-years-of-boardsie/

Windows
-------
You may need to download wget.exe.
Google 'windows wget equivalent' under blogs.
According to http://mgxp.blogspot.com/2010/01/wget-ftp-command-line-download.html:
Download wget.exe from http://users.ugent.be/~bpuype/wget/.

You may need to download php.
www.php.net Download VC6 x86 Non Thread Safe .zip command-line version.
Extract php-5.3.1-nts-Win32-VC6-x86.zip to c:\PHP5.

---------------------------------------------------------------------------------*/

/*---------------------------------------------------------------------------------
findSite()
-----------------------------------------------------------------------------------

The really good tutorial on php regex is
http://www.phpro.org/tutorials/Introduction-to-PHP-Regex.html

---------------------------------------------------------------------------------*/
function findSite( $line, $echoSite )
{
    
    /* Ungreedy preg_match(). */
    if ( preg_match('/<div id="navbar"><a href="(.*?)\/\/(.*?)\//', $line, $matches ) )
    {
	    $site = $matches[1] . $matches[2];
	    if ( $echoSite )
	        echo "<sioc:Site rdf:about=\"" . $site . "\"></sioc:Site>\n";
    	return array( 1, $site );
    }
    else
        return array( 0, 0 );
} /* findSite() */

/*---------------------------------------------------------------------------------
findForum()
---------------------------------------------------------------------------------*/
function findForum( $line, $site, $echoForum )
{
    if ( preg_match('/<div id="navbar"><a href="(.*?)">(.*?)<\/a>/', $line, $matches ) )
    {
        $forum = $matches[1];
        if ( $echoForum )
        {
            echo "<sioc:Forum rdf:about=\"" . $forum . "\">\n";
            echo "  <dcterms:title>" . $matches[2] . "</dcterms:title>\n";
            echo "  <sioc:has_host><sioc:Site rdf:about=\"" . $site . "\"/></sioc:has_host>\n";
            echo "</sioc:Forum>\n";
        }
    	return array( 1, $forum );
    }
    else
        return array( 0, 0 );
} /* findForum() */

/*---------------------------------------------------------------------------------
findThread()
-----------------------------------------------------------------------------------

We return both the text to echo the Thread and the $thread itself.

---------------------------------------------------------------------------------*/
function findThread( $line, $forum )
{
    if ( preg_match('/<p class="largefont">View Full Version : <a href="(.*?)">(.*?)<\/a>/', $line, $matches ) )
    {
    	$thread = $matches[1];
    	$siocThread  = "<sioc:Thread rdf:about=\"" . $thread . "\">\n";
    	$siocThread .= "  <dcterms:title>" . $matches[2] . "</dcterms:title>\n";
        $siocThread .= "  <sioc:has_parent><sioc:Forum rdf:about=\"" . $forum . "\"/></sioc:has_parent>\n";
        $siocThread .= "</sioc:Thread>\n";
    	return array( $siocThread, $thread );
    }
    else
        return array( 0, 0 );
} /* findThread() */

/*---------------------------------------------------------------------------------
findPost()
---------------------------------------------------------------------------------*/
function findPost( $line, $thread )
{
    if ( preg_match('/<div class="post"><div class="posttop">(.*?)<\/div><\/div>/', $line, $matches ) )
    {
        $siocPost = "<sioc:Post>\n";
        if( preg_match( '/<div class="username">(.*?)<\/div>/', $line, $matches ) )
        {
            $siocPost .= "  <sioc:has_container><sioc:Thread rdf:about=\"" . $thread . "\"/></sioc:has_container>\n";
            $siocPost .= "  <sioc:has_creator><sioc:User rdfs:label=\"" . $matches[1] . "\"/></sioc:has_creator>\n";
            return $siocPost;
        }
        else
        {
            fprintf( STDERR, "findPost:  Cannot preg_match() username.\n" );
            fprintf( STDERR, "%s", $line );
            exit();
        }
    }
    return 0;
} /* findPost() */

/*---------------------------------------------------------------------------------
forceUTF8()
-----------------------------------------------------------------------------------

From the W3C, this is the test for being UTF-8:
http://www.w3.org/International/questions/qa-forms-utf-8

$field =~
  m/\A(
     [\x09\x0A\x0D\x20-\x7E]            # ASCII
   | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
   |  \xE0[\xA0-\xBF][\x80-\xBF]        # excluding overlongs
   | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
   |  \xED[\x80-\x9F][\x80-\xBF]        # excluding surrogates
   |  \xF0[\x90-\xBF][\x80-\xBF]{2}     # planes 1-3
   | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
   |  \xF4[\x80-\x8F][\x80-\xBF]{2}     # plane 16
  )*\z/x;

Here are a couple of other interesting sites.
Latin-1 to UTF-8 mapper:
http://www.fischerlaender.net/php/sanitize-utf8-latin1

Map to unicode numbers:
http://randomchaos.com/documents/?source=php_and_unicode

---------------------------------------------------------------------------------*/
function forceUTF8( $text )
{

    /* rabby (see below) notes that preg_match() fails with a segmentation fault
    on very long strings, confirmed by my experience.
    http://mobile-website.mobi/php-utf8-vs-iso-8859-1-59
    Here is the largest factor of two that avoids the segmentation fault on my
    MacBook Pro. */
    if ( 4096 <= strlen( $text ) )
        
        /* Just do it. */
        $text = utf8_encode( $text );
    else
    {

        /* Test whether we really have to utf8_encode() the whole thing.
        "rabby" adapted the W3C UTF-8 standard to this preg_match().
        http://php.net/manual/en/function.utf8-encode.php */
        if ( !preg_match('%^(?:
          [\x09\x0A\x0D\x20-\x7E]            # ASCII
        | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
        |  \xE0[\xA0-\xBF][\x80-\xBF]        # excluding overlongs
        | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
        |  \xED[\x80-\x9F][\x80-\xBF]        # excluding surrogates
        |  \xF0[\x90-\xBF][\x80-\xBF]{2}     # planes 1-3
        | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
        |  \xF4[\x80-\x8F][\x80-\xBF]{2}     # plane 16
        )*$%xs', $text ) )
        {
            $text = utf8_encode( $text );
        }
    }
    return $text;
} /* forceUTF8() */

/*---------------------------------------------------------------------------------
findContent()
---------------------------------------------------------------------------------*/
function findContent( $line, $pile )
{
                    
    /* Hold the $content until we can look at the whole thing. */
	if ( preg_match('/<div class="posttext">(.*)<\/div><\/div>/', $line, $matches ) )
	{
	    if ( $pile != "" ) { fprintf( STDERR, "findContent:  \$pile not empty.\n" ); exit(); }
        $content = $matches[1];
        
        /* We already are enforcing utf-8 in doOneFile(), below.
		$content = forceUTF8( $content );**/
        $siocContent  = "  <sioc:content>\n";
        $siocContent .= $content . "\n";
        $siocContent .= "  </sioc:content>\n";
        /**$siocEOPost = "</sioc:Post>\n";**/
        return array( $siocContent, $content, $pile ); /* $pile is empty. */
	}
	else if ( preg_match('/<div class="posttext">(.*)<br \/>(.*)/U', $line, $matches ) )
	{
        if ( $pile != "" ) { fprintf( STDERR, "findContent:  \$pile not empty.\n" ); exit(); }
        $pile = $matches[1] . "\n" . $matches[2];
        return array( 0, 0, $pile );
	}
	else if ( $pile != "" && preg_match('/(.*)<br \/>(.*)/U', $line, $matches ) )
	{
		$pile = $pile . $matches[1] . "\n" . $matches[2];
		return array( 0, 0, $pile );
	}
	else if ( preg_match('/(.*)<\/div><\/div>/', $line, $matches ) )
	{ 
		$content = $pile . $matches[1];
		$pile    = "";
        /**$content = forceUTF8( $content );**/
        $siocContent  = "  <sioc:content>\n";
        $siocContent .= $content . "\n";
        $siocContent .= "  </sioc:content>\n";
        /**$siocEOPost = "</sioc:Post>\n";**/
        return array( $siocContent, $content, $pile );
	}
} /* findContent() */

/*---------------------------------------------------------------------------------
replaceAmpersand()
---------------------------------------------------------------------------------*/
function replaceAmpersand( $matches )
{
    if ( substr( $matches[0], 0, 1 ) != "&" )
    {
        fprintf( STDERR, "replaceAmpersand:  Not an ampersand.\n" );
        fprintf( STDERR, "%s\n", $matches[1] );
        exit();
    }
    return "&amp;".$matches[1];
}

/*---------------------------------------------------------------------------------
doOneFile()
-----------------------------------------------------------------------------------

Each t-*.html file in the archive holds one Thread.

---------------------------------------------------------------------------------*/
function doOneFile( $directory, $file, $echoSite, $echoForum, $keywords )
{
	$needSite     = 1;
	$needForum    = 0;
	$needThread   = 0;
	$needPost     = 0;
	$needContent  = 0;
	$siocedThread = 0;
	$f = fopen( $directory.$file, "r" );
    while ( $line = fgets( $f, 8192 ) )
    {

        /* www.nomorepanic.co.uk has some non-utf-8 character outside of the
        content, so we might as well enforce utf-8 on everything here.
        Either of these will work.
        $line = forceUTF8(   $line );**/
        $line = utf8_encode( $line ); /* The simpler one. */

        /* We need to patrol for rogue ampersands, those that do not appear in a
        '&([a-zA-Z0-9*?);' pattern.
        The create_function() solution here
        http://php.net/manual/en/function.preg-replace-callback.php
        quickly runs out of memory.
        So we need our own replaceAmpersand(). */
        while ( preg_match( '/&([^a-zA-Z0-9])/', $line ) )
            $line = preg_replace_callback( '/&([^a-zA-Z0-9])/', 'replaceAmpersand', $line );

        /* Now walk through our states. */
        if ( $needSite )
        {
            list( $x, $site ) = findSite( $line, $echoSite );
            if ( $x )
            {
                $needSite  = 0;
                $needForum = 1;
            }
        }
        if ( $needForum )
        {
            list( $x, $forum ) = findForum( $line, $site, $echoForum );
            if ( $x )
            {
                $needForum  = 0;
                $needThread = 1;
            }
        }
        if ( $needThread )
        {
            list( $siocThread, $thread ) = findThread( $line, $forum );
            if (  $siocThread )
            {
                $needThread = 0;
                $needPost   = 1;
            }
        }

        /* We can have several Posts in one Thread. */        
        if ( $needPost )
        {
            $siocPost = findPost( $line, $thread );
            if ( $siocPost )
            {
                $needPost    = 0;
                $needContent = 1;
            }
        }
        if ( $needContent )
        {
            list( $siocContent, $content, $pile ) = findContent( $line, $pile );
            if (  $siocContent )
            {
                if ( $keywords )
                {
                    foreach ( $keywords as $word )
                        if ( preg_match( "/$word/i", $content ) ) /* Ignore case. */
                        {
                            if ( !$siocedThread )
                            {
                            
                                /* We need to echo the $siocThread only once. */
                                echo $siocThread;
                                $siocedThread = 1;
                            }
                            echo $siocPost;
                            echo $siocContent;
                            echo "</sioc:Post>\n";
                            break; /* the foreach. */
                        }
                }
                else
                {
                    if ( !$siocedThread )
                    {
                        echo $siocThread;
                        $siocedThread = 1;
                    }
                    echo $siocPost;
                    echo $siocContent;
                    echo "</sioc:Post>\n";
                }

                /* We may be able to get another Post. */
                $needContent = 0;
                $needPost    = 1;
            }
        }        
    }
    fclose( $f );
} /* doOneFile */

/*---------------------------------------------------------------------------------
main
-----------------------------------------------------------------------------------

We open all the files in the $directory and process all the lines in each file.

---------------------------------------------------------------------------------*/

/* Here is the $directory for the t-*.html files. */
$directory = $argv[1];
if ( $directory == "" )
{
    echo "\n";
    echo "Prototype:\n";
    echo "php -f vBulletin2RDF.php directory [keywords] > output.rdf\n";
    echo "\n";
    echo "Examples:\n";
    echo "php -f vBulletin2RDF.php test/www.sjwinfo.org/forum/archive/index.php/\n";
    echo "php -f vBulletin2RDF.php test/www.sjwinfo.org/forum/archive/index.php/ depression\n";
    echo "php -f vBulletin2RDF.php test/www.sjwinfo.org/forum/archive/index.php/ depression \"side effects\" \n";
    echo "\n";
    exit();
}

/* Here are our optional $keywords in the content. */
$keywords = $argv;
unset( $keywords[ 0 ] ); /* Eliminate the vBulletin2RDF.php argument. */
unset( $keywords[ 1 ] ); /* Eliminate the $directory argument. */
$keywords = array_values( $keywords ); /* Slide the array back to [0]. */

$dir = opendir( $directory );
echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
echo "<rdf:RDF\n";
echo "  xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\"\n";
echo "  xmlns:rdfs=\"http://www.w3.org/2000/01/rdf-schema#\"\n";
echo "  xmlns:dc=\"http://purl.org/dc/elements/1.1/\"\n";
echo "  xmlns:dcterms=\"http://purl.org/dc/terms/\"\n";
echo "  xmlns:sioc=\"http://rdfs.org/sioc/ns#\">\n";

/* We need echo the Site and Forum only once, on the first thread. */
$echoSite  = 1;
$echoForum = 1;
while ( $file = readdir( $dir ) )
{
    
    /* Look for the t-*.html files, for threads.
    Ignore the f-*.html files, for formats. */
    if ( $file != "." && $file != ".." && preg_match( '/^t-/i',     $file ) &&
    /**/                                  preg_match( '/.html\z/i', $file ) )
    {
        doOneFile( $directory, $file, $echoSite, $echoForum, $keywords );
        $echoSite  = 0;
        $echoForum = 0;
        }
    }
echo "</rdf:RDF>\n";
?>
