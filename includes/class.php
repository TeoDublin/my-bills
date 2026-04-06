<?php

    require_once __DIR__.'/../class/Session.php';
    require_once __DIR__.'/../class/Preference.php';
    require_once __DIR__.'/../class/Auth.php';
    require_once __DIR__.'/../class/Mailer.php';
    require_once __DIR__.'/../class/SimpleXlsx.php';
    require_once __DIR__.'/../class/Async.php';

    function Sql(): Sql{

        require_once __DIR__.'/../class/Sql.php';
        return new Sql();

    }

    function Select(string $select):Select{

        require_once __DIR__.'/../class/Select.php';
        return new Select($select);

    }

    function Insert(array $insert):Insert{

        require_once __DIR__.'/../class/Insert.php';
        return new Insert($insert);

    }

    function Update(string $table):Update{

        require_once __DIR__.'/../class/Update.php';
        return new Update($table);

    }

    function Enum(string $table,string $column):Enum{

        require_once __DIR__.'/../class/Enum.php';
        return new Enum($table,$column);

    }

    function Delete():Delete{

        require_once __DIR__.'/../class/Delete.php';
        return new Delete();

    }

    function Session(): Session{

        return Session::get_instance();

    }

    function Preference(): Preference{

        return new Preference();

    }

    function Auth(): Auth{

        static $auth = null;

        if ($auth === null) {

            $auth = new Auth();
        }

        return $auth;

    }

    function Mailer(): Mailer{

        static $mailer = null;

        if ($mailer === null) {

            $mailer = new Mailer();
        }

        return $mailer;

    }

    function SimpleXlsx(): SimpleXlsx{

        static $simple_xlsx = null;

        if ($simple_xlsx === null) {

            $simple_xlsx = new SimpleXlsx();
        }

        return $simple_xlsx;

    }

    function Async(): Async{

        static $async = null;

        if ($async === null) {

            $async = new Async();
        }

        return $async;

    }
