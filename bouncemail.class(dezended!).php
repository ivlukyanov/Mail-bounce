<?php
// Zendecode.com

class APP_BounceMail
{

    protected $timestamp_start = 0;
    protected $timestamp_end = 0;
    public $setPrintLog = TRUE;
    public $mailHost = "localhost";
    public $mailPort = 110;
    public $mailUser = "";
    public $mailPassword = "";
    public $connType = self::CONNECTION_TYPE_POP3;
    public $connPrefix = self::CONNECTION_PREFIX_SIMPLE;
    public $disable_delete = FALSE;
    public $purge_unprocessed = TRUE;
    public $boxName = "INBOX";
    public $moveSoft = FALSE;
    public $boxSoft = "INBOX.soft";
    public $moveHard = FALSE;
    public $boxHard = "INBOX.hard";
    public $deleteMessageDate = "";
    public $max_messages = 100;
    protected $logData = array( );
    protected $lastError = "";
    protected $_mailbox_link = NULL;
    public $use_fetchstructure = TRUE;
    protected $stopProcessing = FALSE;
    protected $c_total = 0;
    protected $c_fetched = 0;
    protected $c_processed = 0;
    protected $c_unprocessed = 0;
    protected $c_deleted = 0;
    protected $c_moved = 0;
    protected $deleteFlag = array( );
    protected $moveFlag = array( );
    protected $rule_categories = array
    (
        "antispam" => array
        (
            "remove" => 0,
            "bounce_type" => "blocked"
        ),
        "autoreply" => array
        (
            "remove" => 0,
            "bounce_type" => "autoreply"
        ),
        "concurrent" => array
        (
            "remove" => 0,
            "bounce_type" => "soft"
        ),
        "content_reject" => array
        (
            "remove" => 0,
            "bounce_type" => "soft"
        ),
        "command_reject" => array
        (
            "remove" => 1,
            "bounce_type" => "hard"
        ),
        "internal_error" => array
        (
            "remove" => 0,
            "bounce_type" => "temporary"
        ),
        "defer" => array
        (
            "remove" => 0,
            "bounce_type" => "soft"
        ),
        "delayed" => array
        (
            "remove" => 0,
            "bounce_type" => "temporary"
        ),
        "dns_loop" => array
        (
            "remove" => 1,
            "bounce_type" => "hard"
        ),
        "dns_unknown" => array
        (
            "remove" => 1,
            "bounce_type" => "hard"
        ),
        "full" => array
        (
            "remove" => 0,
            "bounce_type" => "soft"
        ),
        "inactive" => array
        (
            "remove" => 1,
            "bounce_type" => "hard"
        ),
        "latin_only" => array
        (
            "remove" => 0,
            "bounce_type" => "soft"
        ),
        "other" => array
        (
            "remove" => 1,
            "bounce_type" => "generic"
        ),
        "oversize" => array
        (
            "remove" => 0,
            "bounce_type" => "soft"
        ),
        "outofoffice" => array
        (
            "remove" => 0,
            "bounce_type" => "soft"
        ),
        "unknown" => array
        (
            "remove" => 1,
            "bounce_type" => "hard"
        ),
        "unrecognized" => array
        (
            "remove" => 0,
            "bounce_type" => FALSE
        ),
        "user_reject" => array
        (
            "remove" => 1,
            "bounce_type" => "hard"
        ),
        "warning" => array
        (
            "remove" => 0,
            "bounce_type" => "soft"
        ),
        "confirm_dispath" => array
        (
            "remove" => 0,
            "bounce_type" => "autoreply"
        ),
        "confirm_reading" => array
        (
            "remove" => 0,
            "bounce_type" => "autoreply"
        )
    );
    protected $bodyRules = array( );
    protected $dsnRules = array( );
    protected $stopWork = FALSE;

    const CONNECTION_TYPE_POP3 = "pop3";
    const CONNECTION_TYPE_IMAP = "imap";
    const CONNECTION_PREFIX_SIMPLE = "notls";
    const CONNECTION_PREFIX_TLS = "tls";
    const CONNECTION_PREFIX_SSL = "ssl";

    public function __construct( )
    {
        $this->timestamp_start = $Var_216;
        $this->timestamp_end = $this->timestamp_start + APP_Settings::getobj( "system_time_limit" )->value - 10;
        if ( $this->_blocked_file( 3 ) )
        {
            $this->stopWork = TRUE;
            $this->Log( "Уже работает другая копия модуля. Выключаюсь." );
            $this->printLog( );
            return FALSE;
        }
        $this->_blocked_file( 1 );
        if ( FALSE === ( $data = STF_CacheData::getcachedata( "bmh-rules-dsn" ) ) )
        {
            $ini = new STF_Config_Ini( APP_PATH."/common/files/bmh-rules-dsn.ini", ":" );
            $this->dsnRules = $ini->toArray( );
            STF_CacheData::setcachedata( "bmh-rules-dsn", $this->dsnRules );
            $this->Log( "Правила DSN загружены из конфигурационного файла" );
        }
        else
        {
            $this->dsnRules = $data;
        }
        if ( FALSE === ( $data = STF_CacheData::getcachedata( "bmh-rules-body" ) ) )
        {
            $ini = new STF_Config_Ini( APP_PATH."/common/files/bmh-rules-body.ini", ":" );
            $this->bodyRules = $ini->toArray( );
            STF_CacheData::setcachedata( "bmh-rules-body", $this->bodyRules );
            $this->Log( "Правила BODY загружены из конфигурационного файла" );
        }
        else
        {
            $this->bodyRules = $data;
        }
        $this->Log( "" );
    }

    protected function _blocked_file( $mode = 3 )
    {
        $file = APP_PATH."/_tmp/FETCHLETTER_BLOCK";
        switch ( $mode )
        {
        case 1 :
            if ( !( APP_PATH."/_tmp" ) )
            {
                throw new APP_Exception( "Невозможна запись в каталог _tmp" );
            }
            ( $file, 1 );
            return TRUE;
        case 2 :
            if ( !( $file ) )
            {
                break;
            }
            ( $file );
            return TRUE;
        case 3 :
            if ( ( $file ) && ( $file ) < ( $file ) - 600 )
            {
                return FALSE;
            }
            return ( $file );
        }
        return TRUE;
    }

    public function printLog( )
    {
        if ( !$this->setPrintLog )
        {
            return FALSE;
        }
        $i = 0;
        foreach ( $this->logData as $row )
        {
            ++$i;
            echo "<br/>[".$i."] ".$row['time']." * ".$row['message'];
        }
        $this->logData = array( );
    }

    protected function _return( $result = FALSE )
    {
        $this->_blocked_file( 2 );
        return $result;
    }

    public function openMailbox( )
    {
        if ( $this->stopWork )
        {
            return FALSE;
        }
        if ( ( $this->deleteMessageDate ) != "" )
        {
            $this->Log( "Будут удалены все сообщения до даты ".$this->deleteMessageDate );
        }
        if ( ( $this->mailHost, "gmail" ) )
        {
            $this->moveSoft = FALSE;
            $this->moveHard = FALSE;
        }
        $port = $this->mailPort."/".$this->connType."/".$this->connPrefix;
        if ( isset( APP_Config::getobj( )->fetchmail->connect_options ) )
        {
            $port .= APP_Config::getobj( )->fetchmail->connect_options;
        }
        $this->_mailbox_link = ( "{".$this->mailHost.":".$port."}".$this->boxName, $this->mailUser, $this->mailPassword );
        if ( !$this->_mailbox_link )
        {
            $this->Log( "Не получилось создать ".$this->connType." подключение к ".$this->mailHost );
            $this->Log( "Ошибка: ".$Var_96, TRUE );
            return $this->_return( FALSE );
        }
        $this->Log( "Подключение к ".$this->mailHost." (".$this->mailUser.") произведено успешно!" );
        return $this->_return( TRUE );
    }

    public function processingMailbox( $max = FALSE )
    {
        if ( $this->stopWork )
        {
            return FALSE;
        }
        if ( $this->moveHard && $this->disable_delete === FALSE )
        {
            $this->Log( "Включено принудительно: оставлять сообщения на сервере" );
            $this->disable_delete = TRUE;
        }
        if ( !empty( $max ) )
        {
            $this->max_messages = $max;
        }
        $this->c_total = ( $this->_mailbox_link );
        $this->c_fetched = $this->c_total;
        $this->c_processed = 0;
        $this->c_unprocessed = 0;
        $this->c_deleted = 0;
        $this->c_moved = 0;
        if ( 0 == $this->c_total )
        {
            $this->Log( "Нет сообщений в ящике..." );
        }
        else
        {
            $this->Log( "Всего сообщений в ящике: ".$this->c_total );
            if ( $this->max_messages < $this->c_fetched )
            {
                $this->c_fetched = $this->max_messages;
                $this->Log( "За один запуск обрабатываем ".$this->c_fetched." сообщений" );
            }
            if ( $this->disable_delete )
            {
                if ( $this->moveHard )
                {
                    $this->Log( "Включено hard-перемещение" );
                }
                else
                {
                    $this->Log( "Удаление сообщений выключено. Сообщения будут оставлены в ящике." );
                }
            }
            else
            {
                $this->Log( "Обработанные сообщения будут удалены из ящика" );
            }
            if ( TRUE === $this->checkMoveBoxes( ) )
            {
                $this->Log( "Запущена обработка сообщений" );
                $this->processingMessages( );
                $this->Log( "" );
                $this->Log( "Закончена обработка сообщений" );
            }
            else
            {
                $this->Log( "Ящики для перемещения не существуют и их создать не удалось." );
                $this->Log( "Обработка сообщений производиться не будет." );
            }
        }
        $this->Log( "Закрываем соединение с сервером." );
        @( $this->_mailbox_link );
        ( $this->_mailbox_link );
        if ( 0 < $this->c_total )
        {
            $this->Log( "Принято к обработке сообщений: ".$this->c_fetched );
            $this->Log( "Обработано сообщений: ".$this->c_processed );
            $this->Log( "НЕ Обработано сообщений: ".$this->c_unprocessed );
            $this->Log( "Удалено сообщений: ".$this->c_deleted );
            $this->Log( "Перемещено сообщений: ".$this->c_moved );
        }
        $this->printLog( );
        return $this->_return( TRUE );
    }

    protected function isParameter( $currParameters, $varKey, $varValue )
    {
        foreach ( $currParameters as $key => $value )
        {
            if ( !( $key == $varKey ) && !( $value == $varValue ) )
            {
                continue;
            }
            return TRUE;
        }
        return FALSE;
    }

    protected function checkMoveBoxes( )
    {
        if ( $this->moveHard )
        {
            return $this->mailbox_exist( $this->boxHard );
        }
        if ( $this->moveSoft )
        {
            return $this->mailbox_exist( $this->boxSoft );
        }
        return TRUE;
    }

    protected function processingMessages( )
    {
        $get = STF_Registry::getinstance( )->get;
        $x = 1;
        for ( ; $x <= $this->c_fetched; ++$x )
        {
            $start = STF_Registry::getinstance( )->get;
            $this->Log( "" );
            if ( $this->use_fetchstructure )
            {
                $structure = ( $this->_mailbox_link, $x );
                if ( $structure->type == 1 && $structure->ifsubtype && $structure->subtype == "REPORT" && $structure->ifparameters && $this->isParameter( $structure->parameters, "REPORT-TYPE", "delivery-status" ) )
                {
                    $this->Log( "#".$x.": Сообщение является стандартным DSN сообщением" );
                    $processed = $this->processBounce( $x, "DSN", $this->c_total );
                }
                else
                {
                    $this->Log( "#".$x.": Сообщение НЕ является стандартным DSN сообщением" );
                    $processed = $this->processBounce( $x, "BODY", $this->c_total );
                }
            }
            else
            {
                $header = ( $this->_mailbox_link, $x );
                $match = array( );
                if ( ( "/Content-Type:((?:[^\n]|\n[\t ])+)(?:\n[^\t ]|\$)/is", $header, $match ) )
                {
                    if ( ( "@multipart\\/report@is", $match[1] ) && ( "@report-type=[\"\\']?delivery-status[\"\\']?@is", $match[1] ) )
                    {
                        $processed = $this->processBounce( $x, "DSN", $this->c_total );
                    }
                    else
                    {
                        $this->Log( "#".$x.": Сообщение НЕ является стандартным DSN сообщением" );
                        $processed = $this->processBounce( $x, "BODY", $this->c_total );
                    }
                }
                else
                {
                    $this->Log( "#".$x.": Сообщение не является правильно форматированным MIME-Mail, отсутствует Content-Type" );
                    $processed = $this->processBounce( $x, "BODY", $this->c_total );
                }
            }
            $this->deleteFlag[$x] = FALSE;
            $this->moveFlag[$x] = FALSE;
            if ( $processed )
            {
                $this->c_processed++;
                $this->Log( "#".$x.": Сообщение обработано успешно!" );
                if ( FALSE === $this->disable_delete )
                {
                    @( $this->_mailbox_link, $x );
                    $this->deleteFlag[$x] = TRUE;
                    $this->c_deleted++;
                    $this->Log( "#".$x.": Сообщение удалено из ящика" );
                }
                else if ( $this->moveHard )
                {
                    @( $this->_mailbox_link, $x, $this->boxHard );
                    $this->moveFlag[$x] = TRUE;
                    $this->c_moved++;
                    $this->Log( "#".$x.": Сообщение перемещено в ящик ".$this->boxHard );
                }
                else if ( $this->moveSoft )
                {
                    @( $this->_mailbox_link, $x, $this->boxSoft );
                    $this->moveFlag[$x] = TRUE;
                    $this->c_moved++;
                    $this->Log( "#".$x.": Сообщение перемещено в ящик ".$this->boxSoft );
                }
            }
            else
            {
                $this->c_unprocessed++;
                $this->Log( "#".$x.": Сообщение НЕ обработано!" );
                if ( !$this->disable_delete || $this->purge_unprocessed )
                {
                    @( $this->_mailbox_link, $x );
                    $this->deleteFlag[$x] = TRUE;
                    $this->c_deleted++;
                    $this->Log( "#".$x.": Сообщение удалено из ящика" );
                }
            }
            $this->Log( "Удаляем маркированные для удаления сообщения" );
            @( $this->_mailbox_link );
            $this->Log( "Уменьшаем очередь на 1" );
            --$x;
            $this->c_fetched--;
            if ( isset( $get->sleep ) )
            {
                ( "Засыпаем на ".$this->Log( $get->sleep )." секунд." );
                ( ( $get->sleep ) );
            }
            if ( isset( $get->usleep ) )
            {
                ( "Засыпаем на ".$this->Log( $get->usleep )." микросекунд." );
                ( ( $get->usleep ) );
            }
            if ( !( $this->timestamp_end <= $Var_72 ) )
            {
                continue;
            }
            $this->Log( "Время работы программы закончилось." );
            break;
        }
    }

    public function processBounce( $pos, $type, $totalFetched )
    {
        $this->Log( "#".$pos.": Началась обработка сообщения" );
        $header = ( $this->_mailbox_link, $pos );
        $subject = $this->decodeSubject( $header->subject );
        $this->Log( "#".$pos.": Тема сообщения : ".$subject );
        $result = NULL;
        if ( $type == "DSN" )
        {
            $dsn_msg = ( $this->_mailbox_link, $pos, "1" );
            $dsn_msg_structure = ( $this->_mailbox_link, $pos, "1" );
            $dsn_msg = $this->decodeBody( $dsn_msg_structure->encoding, $dsn_msg );
            $dsn_report = ( $this->_mailbox_link, $pos, "2" );
            $result = $this->bmhDSNRules( $dsn_msg, $dsn_report );
        }
        else if ( $type == "BODY" )
        {
            $structure = ( $this->_mailbox_link, $pos );
            switch ( $structure->type )
            {
            case 0 :
                $body = ( $this->_mailbox_link, $pos );
                $body = $this->decodeBody( $structure->encoding, $body );
                $result = $this->bmhBodyRules( $body, $structure );
                if ( !( "0000" == $result['rule_no'] ) )
                {
                    break;
                }
                $result = $this->checkConfirmationBounce( $structure, $pos, $subject, $header );
                break;
            case 1 :
                $body = ( $this->_mailbox_link, $pos, "1" );
                $body = $this->decodeBody( $structure->encoding, $body );
                $result = $this->bmhBodyRules( $body, $structure );
                if ( !( "0000" == $result['rule_no'] ) )
                {
                    break;
                }
                $result = $this->checkConfirmationBounce( $structure, $pos, $subject, $header );
                break;
            case 2 :
                $body = ( $this->_mailbox_link, $pos );
                $body = $this->decodeBody( $structure->encoding, $body );
                $result = $this->bmhBodyRules( $body, $structure );
                if ( !( "0000" == $result['rule_no'] ) )
                {
                    break;
                }
                $result = $this->checkConfirmationBounce( $structure, $pos, $subject, $header );
                break;
            default :
                $this->Log( "#".$pos.": Сообщение имеет не поддерживаемый Content-Type:".$structure->type );
                return FALSE;
            }
        }
        else
        {
            $this->Log( "Внутренняя ошибка: неизвестный тип сообщения" );
            return FALSE;
        }
        if ( ( $result ) )
        {
            $this->Log( "#".$pos.": Для сообщения результат не получен..." );
            return FALSE;
        }
        if ( ( $result['email'] ) == "" )
        {
            $result['email'] = $this->getEmailFromStr( $header->fromaddress );
        }
        if ( $this->moveHard && $result['remove'] == 1 )
        {
            $result['remove'] = "moved (hard)";
        }
        else if ( $this->moveSoft && $result['remove'] == 1 )
        {
            $result['remove'] = "moved (soft)";
        }
        else if ( $this->disable_delete )
        {
            $result['remove'] = 0;
        }
        if ( "0000" == $result['rule_no'] )
        {
            $this->Log( "#".$pos.": НЕ Определено правило для сообщения, E-mail: ".$result['email'] );
            $non_rules = array(
                "@^Re(?:\\[\\d+\\])?:@si" => $subject
            );
            $err = 0;
            foreach ( $non_rules as $tpl => $source )
            {
                if ( !( $tpl, $source ) )
                {
                    continue;
                }
                ++$err;
                break;
            }
            if ( ( "@Reading.Confirmation@si", $subject ) && ( "@\\@mail\\.ru@si", $result['email'] ) )
            {
                ++$err;
            }
            if ( 0 == $err )
            {
                $mess_headers = ( $this->_mailbox_link, $pos );
                $body = ( $body, 0, 4000 );
                APP_BaseSiteConnector::sendunknownbouncemail( $body, $mess_headers );
            }
            return FALSE;
        }
        $this->Log( "#".$pos.": Определено правило для сообщения:" );
        $this->Log( "Номер: ".$result['rule_no'].", Категория: ".$result['rule_cat'].", Тип сообщения: ".$result['bounce_type'].", E-mail: ".$result['email'] );
        $this->action( $pos, $result, $subject, $body, $header );
        return TRUE;
    }

    protected function getEmailFromStr( $str = "" )
    {
        $match = array( );
        $tpl = "@<?((?:[\\w\\!\\#\\\$\\%\\&'\\*\\+\\-\\/\\=\\?\\^\\`\\{\\|\\}\\~]+\\.)*[\\w\\!\\#\\\$\\%\\&'\\*\\+\\-\\/\\=\\?\\^\\`\\{\\|\\}\\~]+\\@(?:(?:(?:[a-zA-Z0-9_](?:[a-zA-Z0-9_\\-](?!\\.)){0,61}[a-zA-Z0-9_-]?\\.)+[a-zA-Z0-9_](?:[a-zA-Z0-9_\\-](?!\$)){0,61}[a-zA-Z0-9_]?)|(?:\\[(?:(?:[01]?\\d{1,2}|2[0-4]\\d|25[0-5])\\.){3}(?:[01]?\\d{1,2}|2[0-4]\\d|25[0-5])\\])))>?@is";
        if ( ( $tpl, $str, $match ) )
        {
            return $match[1];
        }
        return FALSE;
    }

    protected function decodeBody( $encode_type = 1, $body )
    {
        if ( $encode_type == 4 )
        {
            return ( $body );
        }
        if ( $encode_type == 3 )
        {
            return ( $body );
        }
        return $body;
    }

    protected function decodeSubject( $subject = "" )
    {
        if ( "=?" == ( $subject, 0, 2 ) )
        {
            $tpl = "@\\=\\?([-a-zA-Z0-9]+)\\?(B|Q)\\?(.+)\\?\\=@si";
            $match = array( );
            if ( ( $tpl, $subject, $match ) )
            {
                $codepage = $match[1];
                $encode = $match[2];
                $subject = $match[3];
                if ( "B" == ( $encode ) )
                {
                    $subject = ( $subject );
                }
                else if ( "Q" == ( $encode ) )
                {
                    $subject = ( $subject );
                }
                if ( "UTF-8" != ( $codepage ) )
                {
                    $subject = ( $subject, "UTF-8", ( $codepage ) );
                }
                $subject = ( $subject, "UTF-8", ( $codepage ) );
            }
        }
        return $subject;
    }

    protected function checkConfirmationBounce( $structure, $pos, $subject, $header )
    {
        $result = array( "email" => "", "bounce_type" => FALSE, "remove" => 0, "rule_cat" => "unrecognized", "rule_no" => "0000" );
        $info = FALSE;
        $notification = "";
        $completed = FALSE;
        if ( isset( $structure->parts[1] ) && "DISPOSITION-NOTIFICATION" == $structure->parts[1]->subtype )
        {
            $body_part2 = ( $this->_mailbox_link, $pos, "2" );
            $tpl = "@Disposition:\\s?automatic-action/MDN-sent-automatically\r\n\t\t\t;\\s?(displayed|dispatched|processed)@si";
            $match = array( );
            if ( ( $tpl, $body_part2, $match ) )
            {
                $dType = ( $match[1] );
                $notification = $dType;
                if ( isset( $structure->parts[2] ) && "RFC822-HEADER" == $structure->parts[2]->subtype )
                {
                    $body_part3 = ( $this->_mailbox_link, $pos, "3" );
                    $info = $this->getSystemInfoFromRFCHeaders( $body_part3 );
                }
                if ( FALSE === $info )
                {
                    if ( isset( $header->in_reply_to ) && "" != $header->in_reply_to )
                    {
                        $info = $this->getSystemInfoFromMessageID( $header->in_reply_to );
                    }
                    else if ( isset( $header->references ) && "" != $header->references )
                    {
                        $info = $this->getSystemInfoFromMessageID( $header->references );
                    }
                }
                if ( ( $info ) )
                {
                    $completed = TRUE;
                }
            }
        }
        else
        {
            $tpl = "@Reading.Confirmation@si";
            if ( ( $tpl, $subject ) )
            {
                if ( isset( $header->in_reply_to ) && "" != $header->in_reply_to )
                {
                    $info = $this->getSystemInfoFromMessageID( $header->in_reply_to );
                }
                else if ( isset( $header->references ) && "" != $header->references )
                {
                    $info = $this->getSystemInfoFromMessageID( $header->references );
                }
                $notification = "displayed";
                if ( ( $info ) )
                {
                    $completed = TRUE;
                }
            }
        }
        if ( TRUE === $completed )
        {
            if ( "displayed" == $notification || "processed" == $notification )
            {
                $result['rule_cat'] = "confirm_reading";
                $result['rule_no'] = "5001";
                $result['bounce_type'] = $this->rule_categories['confirm_reading']['bounce_type'];
            }
            else if ( "dispatched" == $notification )
            {
                $result['rule_cat'] = "confirm_dispath";
                $result['rule_no'] = "5002";
                $result['bounce_type'] = $this->rule_categories['confirm_dispath']['bounce_type'];
            }
            $result['system_info'] = $info;
        }
        return $result;
    }

    protected function getSystemInfoFromRFCHeaders( $rfc_headers )
    {
        $info = array( );
        $hArr = $this->getStr( "@X-Mailing-List:\\s?(\\d+)-(\\d+)-(\\d+)-(\\d)-(HTML|TEXT)-([-a-zA-Z0-9]+)@si", $rfc_headers );
        if ( FALSE !== $hArr )
        {
            $info['codepage'] = $hArr[6];
            $info['bodytype'] = $hArr[5];
            $info['system_flag'] = $hArr[4];
            $info['mailing_id'] = $hArr[3];
            $info['letter_id'] = $hArr[2];
            $info['subscriber_id'] = $hArr[1];
            $info['header'] = $hArr[0];
            return $info;
        }
        return FALSE;
    }

    protected function getSystemInfoFromMessageID( $message_id )
    {
        $info = array( );
        $tpl = "@<?Q(\\d+)\\.SB(\\d+)\\.LT(\\d+)\\.T(\\d+)\\@\\S+\\w>?@si";
        $match = array( );
        if ( ( $tpl, $message_id, $match ) )
        {
            $info['timestamp'] = $match[4];
            $info['letter_id'] = $match[3];
            $info['subscriber_id'] = $match[2];
            $info['queue_id'] = $match[1];
            $info['message_id'] = $match[0];
            return $info;
        }
        return FALSE;
    }

    protected function action( $pos = 0, $result, $subject = "", $body = "", $header )
    {
        $bounce_settings = APP_Config::getobj( )->bouncemails;
        $setName = "deactivate_".$result['bounce_type'];
        if ( !isset( $result['system_info'] ) )
        {
            $result['system_info'] = FALSE;
            $result['system_info'] = $this->getSystemInfoFromRFCHeaders( $body );
            if ( !$result['system_info'] )
            {
                if ( isset( $header->in_reply_to ) && "" != $header->in_reply_to )
                {
                    $result['system_info'] = $this->getSystemInfoFromMessageID( $header->in_reply_to );
                }
                else if ( isset( $header->references ) && "" != $header->references )
                {
                    $result['system_info'] = $this->getSystemInfoFromMessageID( $header->references );
                }
            }
        }
        if ( ( $result['system_info'] ) )
        {
            if ( 1 == $bounce_settings->$setName )
            {
                $subscriberObj = APP_Entity_Subscriber::getinstance( );
                $subscriberObj->loadByField( "id", $result['system_info']['subscriber_id'] );
                if ( 0 < $subscriberObj->id )
                {
                    $subscriberObj->setManualSave( );
                    $subscriberObj->bounce_rule = $result['rule_no'];
                    $subscriberObj->bounce_cat = $result['rule_cat'];
                    $subscriberObj->bounce_type = $result['bounce_type'];
                    $subscriberObj->deactivated = 1;
                    $subscriberObj->deactivated_reason = 1;
                    $subscriberObj->Save( );
                    $this->Log( "Подписчик ".$subscriberObj->id." деактивирован!" );
                    APP_Entity_LetterStat::add( APP_Entity_LetterStat::TYPE_SUBSCRIBER_DEACTIVATED, $result['system_info']['letter_id'], $result['system_info']['subscriber_id'] );
                }
            }
            if ( "autoreply" != $result['bounce_type'] )
            {
                $letterObj = APP_Entity_Letter::getinstance( $result['system_info']['letter_id'] );
                $letterObj->incrementValue( "stat_delivery_error", 1 );
                $this->Log( "Письмо ".$result['system_info']['letter_id']." подписчику ".$result['system_info']['subscriber_id']." не доставлено!" );
                APP_Entity_LetterStat::add( APP_Entity_LetterStat::TYPE_DELIVERY_ERROR, $result['system_info']['letter_id'], $result['system_info']['subscriber_id'] );
            }
            else if ( "confirm_reading" == $result['rule_cat'] )
            {
                $letterObj = APP_Entity_Letter::getinstance( $result['system_info']['letter_id'] );
                $letterObj->incrementValue( "stat_cr", 1 );
                $this->Log( "Письмо ".$result['system_info']['letter_id']." подписчику ".$result['system_info']['subscriber_id'].": ПОДТВЕРЖДЕНИЕ ПРОЧТЕНИЯ" );
                APP_Entity_LetterStat::add( APP_Entity_LetterStat::TYPE_CONFIRM_READING, $result['system_info']['letter_id'], $result['system_info']['subscriber_id'] );
            }
            else if ( "confirm_dispath" == $result['rule_cat'] )
            {
                $letterObj = APP_Entity_Letter::getinstance( $result['system_info']['letter_id'] );
                $letterObj->incrementValue( "stat_cd", 1 );
                $this->Log( "Письмо ".$result['system_info']['letter_id']." подписчику ".$result['system_info']['subscriber_id'].": ПОДТВЕРЖДЕНИЕ ДОСТАВКИ" );
                APP_Entity_LetterStat::add( APP_Entity_LetterStat::TYPE_CONFIRM_READING, $result['system_info']['letter_id'], $result['system_info']['subscriber_id'] );
            }
        }
    }

    protected function mailbox_exist( $mailbox, $create = TRUE )
    {
        if ( ( $mailbox ) == "" || !( $mailbox, "INBOX." ) )
        {
            $this->Log( "Ошибка в имени ящика для операции перемещения. Продолжение невозможно." );
            $this->Log( "СОВЕТ: имя ящика должно удовлетворять форме \"INBOX.MailboxName\"" );
            $this->stopProcessing = TRUE;
            return FALSE;
        }
        $this->Log( "Проверяем наличие ящика ".$mailbox." для перемещения сообщений" );
        $port = $this->mailPort."/".$this->connType."/".$this->connPrefix;
        $mbox = ( "{".$this->mailHost.":".$port."}", $this->mailUser, $this->mailPassword, OP_HALFOPEN );
        if ( !$mbox )
        {
            $this->Log( "Не получилось создать ".$this->connType." подключение к ".$this->mailHost );
            $this->Log( "Ошибка: ".$Var_144, TRUE );
            return FALSE;
        }
        $this->Log( "Подключение к ".$this->mailHost." (".$this->mailUser.") произведено успешно!" );
        $list = ( $mbox, "{".$this->mailHost.":".$port."}", "*" );
        $mailboxFound = FALSE;
        if ( ( $list ) )
        {
            foreach ( ( $list ) as $val )
            {
                $nameArr = ( ( 125 ), ( $val->name ) );
                $nameRaw = $nameArr[( $nameArr ) - 1];
                if ( $mailbox == $nameRaw )
                {
                    $mailboxFound = TRUE;
                    $this->Log( "Ящик для перемещения сообщений найден" );
                }
            }
            if ( $mailboxFound === FALSE && $create )
            {
                $this->Log( "Ящик для перемещения сообщений не найден. Попытка создать его." );
                @( $mbox, @( "{".$this->mailhost.":".$port."}".$mailbox ) );
                ( $mbox );
                return TRUE;
            }
            $this->Log( "Ящик для перемещения сообщений не найден. Указано не создавать ящик." );
            ( $mbox );
            return FALSE;
        }
        $this->Log( "Ящиков не найдено на сервере." );
        ( $mbox );
        return FALSE;
    }

    protected function bmhDSNRules( $dsn_msg, $dsn_report )
    {
        $result = array( "email" => "", "bounce_type" => FALSE, "remove" => 0, "rule_cat" => "unrecognized", "rule_no" => "0000" );
        $action = FALSE;
        $status_code = FALSE;
        $diag_code = FALSE;
        $match = array( );
        if ( ( "/Original-Recipient: rfc822;(.*)/i", $dsn_report, $match ) )
        {
            $email_arr = ( $match[1], "default.do".__FILE__.".name" );
            if ( isset( $email_arr[0]->host ) && $email_arr[0]->host != ".SYNTAX-ERROR." && $email_arr[0]->host != "default.do".__FILE__.".name" )
            {
                $result['email'] = $email_arr[0]->mailbox."@".$email_arr[0]->host;
            }
        }
        else if ( ( "/Final-Recipient: rfc822;(.*)/i", $dsn_report, $match ) )
        {
            $email_arr = ( $match[1], "default.do".__FILE__.".name" );
            if ( isset( $email_arr[0]->host ) && $email_arr[0]->host != ".SYNTAX-ERROR." && $email_arr[0]->host != "default.do".__FILE__.".name" )
            {
                $result['email'] = $email_arr[0]->mailbox."@".$email_arr[0]->host;
            }
        }
        if ( ( "/Action: (.+)/i", $dsn_report, $match ) )
        {
            $action = ( ( $match[1] ) );
        }
        if ( ( "/Status: ([0-9\\.]+)/i", $dsn_report, $match ) )
        {
            $status_code = $match[1];
        }
        if ( ( "/Diagnostic-Code:((?:[^\n]|\n[\t ])+)(?:\n[^\t ]|\$)/is", $dsn_report, $match ) )
        {
            $diag_code = $match[1];
        }
        if ( empty( $result['email'] ) )
        {
            if ( ( "/quota exceed.*<(\\S+@\\S+\\w)>/is", $dsn_msg, $match ) )
            {
                $result['rule_cat'] = "full";
                $result['rule_no'] = "3000";
                $result['email'] = $match[1];
                return $result;
            }
        }
        else
        {
            switch ( $action )
            {
            case "failed" :
                foreach ( $this->dsnRules as $key => $params )
                {
                    list( $rule, $cat ) = ( "_", $key, 2 );
                    $rule = ( $rule, 1 );
                    if ( !( $params['tpl'], $diag_code ) )
                    {
                        continue;
                    }
                    $this->Log( "Совпавшее правило: ".$params['tpl'] );
                    $result['rule_cat'] = $cat;
                    $result['rule_no'] = $rule;
                    break;
                }
                break;
            case "delayed" :
                $result['rule_cat'] = "delayed";
                $result['rule_no'] = "4000";
                break;
            case "delivered" :
            case "relayed" :
            case "expanded" :
            }
            if ( $result['rule_no'] == "0000" )
            {
                $this->Log( "" );
                $this->Log( "*** E-mail: ".$result['email'] );
                $this->Log( "*** Action: ".$action );
                $this->Log( "*** Status: ".$status_code );
                $this->Log( "*** Diagnostic-Code: ".$diag_code );
                $this->Log( "*** DSN Message: ".$dsn_msg );
                $this->Log( "" );
                return $result;
            }
            if ( $result['bounce_type'] === FALSE )
            {
                $result['bounce_type'] = $this->rule_categories[$result['rule_cat']]['bounce_type'];
                $result['remove'] = $this->rule_categories[$result['rule_cat']]['remove'];
            }
        }
        return $result;
    }

    protected function bmhBodyRules( $body, $structure )
    {
        $result = array( "email" => "", "bounce_type" => FALSE, "remove" => 0, "rule_cat" => "unrecognized", "rule_no" => "0000" );
        $body = ( $body, 0, 4000 );
        foreach ( $this->bodyRules as $key => $params )
        {
            list( $rule, $cat ) = ( "_", $key, 2 );
            $rule = ( $rule, 1 );
            $match = array( );
            if ( !( $params['tpl'], $body, $match ) )
            {
                continue;
            }
            $this->Log( "Совпавшее правило: ".$params['tpl'] );
            $result['rule_cat'] = $cat;
            $result['rule_no'] = $rule;
            $result['email'] = $match[1];
            break;
        }
        if ( !( $result['rule_no'] == "0000" ) || $result['bounce_type'] === FALSE )
        {
            $result['bounce_type'] = $this->rule_categories[$result['rule_cat']]['bounce_type'];
            $result['remove'] = $this->rule_categories[$result['rule_cat']]['remove'];
        }
        return $result;
    }

    protected function getStr( $tpl_header = "", $body = "" )
    {
        if ( empty( $tpl_header ) )
        {
            return FALSE;
        }
        $matches = array( );
        if ( ( $tpl_header, $body, $matches ) )
        {
            return $matches;
        }
        return FALSE;
    }

    protected function Log( $message, $error = FALSE )
    {
        $this->logData[] = array(
            "time" => STF_Utils_DateTime::getnowmysqlformat( ),
            "message" => $message
        );
        if ( $error )
        {
            $this->lastError = $message;
        }
        ( APP_PATH."/_tmp/fetch.log", "[".( $this->logData )."] ".STF_Utils_DateTime::getnowmysqlformat( )." * ".$message."\n", FILE_APPEND );
    }

}

?>
