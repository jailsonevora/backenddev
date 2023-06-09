<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit6a42a0ec831ed86a9dc1d3ec6205a61f
{
    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'StaticHTMLOutput\\' => 17,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'StaticHTMLOutput\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $prefixesPsr0 = array (
        'S' => 
        array (
            'Sabberworm\\CSS' => 
            array (
                0 => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib',
            ),
        ),
        'F' => 
        array (
            'FtpClient' => 
            array (
                0 => __DIR__ . '/..' . '/nicolab/php-ftp-client/src',
            ),
        ),
    );

    public static $classMap = array (
        'FtpClient\\FtpClient' => __DIR__ . '/..' . '/nicolab/php-ftp-client/src/FtpClient/FtpClient.php',
        'FtpClient\\FtpException' => __DIR__ . '/..' . '/nicolab/php-ftp-client/src/FtpClient/FtpException.php',
        'FtpClient\\FtpWrapper' => __DIR__ . '/..' . '/nicolab/php-ftp-client/src/FtpClient/FtpWrapper.php',
        'Net_URL2' => __DIR__ . '/..' . '/pear/net_url2/Net/URL2.php',
        'Sabberworm\\CSS\\CSSList\\AtRuleBlockList' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/CSSList/AtRuleBlockList.php',
        'Sabberworm\\CSS\\CSSList\\CSSBlockList' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/CSSList/CSSBlockList.php',
        'Sabberworm\\CSS\\CSSList\\CSSList' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/CSSList/CSSList.php',
        'Sabberworm\\CSS\\CSSList\\Document' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/CSSList/Document.php',
        'Sabberworm\\CSS\\CSSList\\KeyFrame' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/CSSList/KeyFrame.php',
        'Sabberworm\\CSS\\Comment\\Comment' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Comment/Comment.php',
        'Sabberworm\\CSS\\Comment\\Commentable' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Comment/Commentable.php',
        'Sabberworm\\CSS\\OutputFormat' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/OutputFormat.php',
        'Sabberworm\\CSS\\OutputFormatter' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/OutputFormat.php',
        'Sabberworm\\CSS\\Parser' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Parser.php',
        'Sabberworm\\CSS\\Parsing\\OutputException' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Parsing/OutputException.php',
        'Sabberworm\\CSS\\Parsing\\ParserState' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Parsing/ParserState.php',
        'Sabberworm\\CSS\\Parsing\\SourceException' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Parsing/SourceException.php',
        'Sabberworm\\CSS\\Parsing\\UnexpectedTokenException' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Parsing/UnexpectedTokenException.php',
        'Sabberworm\\CSS\\Property\\AtRule' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Property/AtRule.php',
        'Sabberworm\\CSS\\Property\\CSSNamespace' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Property/CSSNamespace.php',
        'Sabberworm\\CSS\\Property\\Charset' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Property/Charset.php',
        'Sabberworm\\CSS\\Property\\Import' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Property/Import.php',
        'Sabberworm\\CSS\\Property\\Selector' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Property/Selector.php',
        'Sabberworm\\CSS\\Renderable' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Renderable.php',
        'Sabberworm\\CSS\\RuleSet\\AtRuleSet' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/RuleSet/AtRuleSet.php',
        'Sabberworm\\CSS\\RuleSet\\DeclarationBlock' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/RuleSet/DeclarationBlock.php',
        'Sabberworm\\CSS\\RuleSet\\RuleSet' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/RuleSet/RuleSet.php',
        'Sabberworm\\CSS\\Rule\\Rule' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Rule/Rule.php',
        'Sabberworm\\CSS\\Settings' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Settings.php',
        'Sabberworm\\CSS\\Value\\CSSFunction' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Value/CSSFunction.php',
        'Sabberworm\\CSS\\Value\\CSSString' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Value/CSSString.php',
        'Sabberworm\\CSS\\Value\\CalcFunction' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Value/CalcFunction.php',
        'Sabberworm\\CSS\\Value\\CalcRuleValueList' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Value/CalcRuleValueList.php',
        'Sabberworm\\CSS\\Value\\Color' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Value/Color.php',
        'Sabberworm\\CSS\\Value\\LineName' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Value/LineName.php',
        'Sabberworm\\CSS\\Value\\PrimitiveValue' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Value/PrimitiveValue.php',
        'Sabberworm\\CSS\\Value\\RuleValueList' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Value/RuleValueList.php',
        'Sabberworm\\CSS\\Value\\Size' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Value/Size.php',
        'Sabberworm\\CSS\\Value\\URL' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Value/URL.php',
        'Sabberworm\\CSS\\Value\\Value' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Value/Value.php',
        'Sabberworm\\CSS\\Value\\ValueList' => __DIR__ . '/..' . '/sabberworm/php-css-parser/lib/Sabberworm/CSS/Value/ValueList.php',
        'StaticHTMLOutput\\Archive' => __DIR__ . '/../..' . '/src/Archive.php',
        'StaticHTMLOutput\\ArchiveProcessor' => __DIR__ . '/../..' . '/src/ArchiveProcessor.php',
        'StaticHTMLOutput\\BitBucket' => __DIR__ . '/../..' . '/src/BitBucket.php',
        'StaticHTMLOutput\\BunnyCDN' => __DIR__ . '/../..' . '/src/BunnyCDN.php',
        'StaticHTMLOutput\\CLI' => __DIR__ . '/../..' . '/src/CLI.php',
        'StaticHTMLOutput\\CSSProcessor' => __DIR__ . '/../..' . '/src/CSSProcessor.php',
        'StaticHTMLOutput\\Controller' => __DIR__ . '/../..' . '/src/Controller.php',
        'StaticHTMLOutput\\DBSettings' => __DIR__ . '/../..' . '/src/DBSettings.php',
        'StaticHTMLOutput\\Deployer' => __DIR__ . '/../..' . '/src/Deployer.php',
        'StaticHTMLOutput\\Exporter' => __DIR__ . '/../..' . '/src/Exporter.php',
        'StaticHTMLOutput\\FTP' => __DIR__ . '/../..' . '/src/FTP.php',
        'StaticHTMLOutput\\FileCopier' => __DIR__ . '/../..' . '/src/FileCopier.php',
        'StaticHTMLOutput\\FileWriter' => __DIR__ . '/../..' . '/src/FileWriter.php',
        'StaticHTMLOutput\\FilesHelper' => __DIR__ . '/../..' . '/src/FilesHelper.php',
        'StaticHTMLOutput\\GitHub' => __DIR__ . '/../..' . '/src/GitHub.php',
        'StaticHTMLOutput\\GitLab' => __DIR__ . '/../..' . '/src/GitLab.php',
        'StaticHTMLOutput\\HTMLProcessor' => __DIR__ . '/../..' . '/src/HTMLProcessor.php',
        'StaticHTMLOutput\\MimeTypes' => __DIR__ . '/../..' . '/src/MimeTypes.php',
        'StaticHTMLOutput\\Netlify' => __DIR__ . '/../..' . '/src/Netlify.php',
        'StaticHTMLOutput\\Options' => __DIR__ . '/../..' . '/src/Options.php',
        'StaticHTMLOutput\\PostSettings' => __DIR__ . '/../..' . '/src/PostSettings.php',
        'StaticHTMLOutput\\ProgressLog' => __DIR__ . '/../..' . '/src/ProgressLog.php',
        'StaticHTMLOutput\\Request' => __DIR__ . '/../..' . '/src/Request.php',
        'StaticHTMLOutput\\S3' => __DIR__ . '/../..' . '/src/S3.php',
        'StaticHTMLOutput\\SiteCrawler' => __DIR__ . '/../..' . '/src/SiteCrawler.php',
        'StaticHTMLOutput\\SitePublisher' => __DIR__ . '/../..' . '/src/SitePublisher.php',
        'StaticHTMLOutput\\StaticHTMLOutput' => __DIR__ . '/../..' . '/src/StaticHTMLOutput.php',
        'StaticHTMLOutput\\StaticHTMLOutputException' => __DIR__ . '/../..' . '/src/StaticHTMLOutputException.php',
        'StaticHTMLOutput\\TXTProcessor' => __DIR__ . '/../..' . '/src/TXTProcessor.php',
        'StaticHTMLOutput\\TemplateHelper' => __DIR__ . '/../..' . '/src/TemplateHelper.php',
        'StaticHTMLOutput\\View' => __DIR__ . '/../..' . '/src/View.php',
        'StaticHTMLOutput\\WPSite' => __DIR__ . '/../..' . '/src/WPSite.php',
        'StaticHTMLOutput\\WsLog' => __DIR__ . '/../..' . '/src/WsLog.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit6a42a0ec831ed86a9dc1d3ec6205a61f::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit6a42a0ec831ed86a9dc1d3ec6205a61f::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInit6a42a0ec831ed86a9dc1d3ec6205a61f::$prefixesPsr0;
            $loader->classMap = ComposerStaticInit6a42a0ec831ed86a9dc1d3ec6205a61f::$classMap;

        }, null, ClassLoader::class);
    }
}
