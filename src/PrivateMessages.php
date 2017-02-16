<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2012 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunPMS;

use RunBB\Middleware\Logged;
use RunBB\Core\Plugin;
use RunBB\Core\Utils;

class PrivateMessages extends Plugin
{
    const NAME = 'pms';// config key name
    const TITLE = 'Private Messages';
    const DESCRIPTION = 'Private Messages based on <a href="https://github.com/featherbb/private-messages">'.
        'featherbb/private-messages</a> v.0.2.3';
    const VERSION = '0.2.4';
    const KEYWORDS = [
        'runbb',
        'private messages',
        'pms',
        'helper',
        'messages'
    ];
    const AUTHOR = [
        'name' => '1f7'
    ];

    /**
     * Back compatibility with featherBB plugins
     *
     * @return string
     */
    public static function getInfo()
    {
        $cfg = [//TODO rebuild use composer.json ?
            'name' => self::NAME,// config key name
            'title' => self::TITLE,
            'description' => self::DESCRIPTION,
            'version' => self::VERSION,
            'keywords' => self::KEYWORDS,
            'author' => self::AUTHOR
        ];
        return json_encode($cfg);
    }

    public static function adminMenu()
    {
        Lang::load('private-messages', 'pms', __DIR__ . '/lang');

        return [
            'url' => 'pms',
            'name' => d__('pms', 'PMS')
        ];
    }

    public function run()
    {
        Statical::addNamespace('*', __NAMESPACE__.'\\*');

//        Container::get('hooks')->bind('admin.plugin.menu', [$this, 'getName']);
        $this->c['hooks']->bind('admin.plugin.menu', [$this, 'getAdminMenu']);
        $this->c['hooks']->bind('view.header.navlinks', [$this, 'addNavlink']);
        $this->c['hooks']->bind('model.print_posts.one', function ($cur_post) {
            $cur_post['user_contacts'][] = '<span class="email"><a href="' .
                $this->c['router']->pathFor(
                    'Conversations.send',
                    ['uid' => $cur_post['poster_id']]
                ) . '">PM</a></span>';
            return $cur_post;
        });

        Route::group('/forum/pms', function () {
            $this->map(
                ['GET', 'POST'],
                '/inbox[/{inbox_id:[0-9]+}]',
                '\RunPMS\Controller\PrivateMessages:index'
            )->setName('Conversations.home');
            $this->map(
                ['GET', 'POST'],
                '/inbox/{inbox_id:[0-9]+}/page/{page:[0-9]+}',
                '\RunPMS\Controller\PrivateMessages:index'
            )->setName('Conversations.home.page');
            $this->get(
                '/thread/{tid:[0-9]+}',
                '\RunPMS\Controller\PrivateMessages:show'
            )->setName('Conversations.show');
            $this->get(
                '/thread/{tid:[0-9]+}/page/{page:[0-9]+}',
                '\RunPMS\Controller\PrivateMessages:show'
            )->setName('Conversations.show.page');
            $this->map(
                ['GET', 'POST'],
                '/send[/{uid:[0-9]+}]',
                '\RunPMS\Controller\PrivateMessages:send'
            )->setName('Conversations.send');
            $this->map(
                ['GET', 'POST'],
                '/reply/{tid:[0-9]+}',
                '\RunPMS\Controller\PrivateMessages:reply'
            )->setName('Conversations.reply');
            $this->map(
                ['GET', 'POST'],
                '/quote/{mid:[0-9]+}',
                '\RunPMS\Controller\PrivateMessages:reply'
            )->setName('Conversations.quote');
            $this->map(
                ['GET', 'POST'],
                '/options/blocked',
                '\RunPMS\Controller\PrivateMessages:blocked'
            )->setName('Conversations.blocked');
            $this->map(
                ['GET', 'POST'],
                '/options/folders',
                '\RunPMS\Controller\PrivateMessages:folders'
            )->setName('Conversations.folders');
        })->add(new Logged());

        View::addAsset(
            'css',
            $this->c['forum_env']['WEB_PLUGINS'].'/'.self::NAME . '/private-messages.css',
            ['type' => 'text/css', 'rel' => 'stylesheet']
        );
    }

    public function addNavlink($navlinks)
    {
        // file, domain, path
        Lang::load('private-messages', 'pms', __DIR__ . '/lang');
        if (!User::get()->is_guest) {
            $nbUnread = Model\PrivateMessages::countUnread(User::get()->id);
            $count = ($nbUnread > 0) ? ' (' . $nbUnread . ')' : '';
            $navlinks[] = '4 = <a href="' . $this->c['router']->pathFor('Conversations.home') .
                '">PMS' . $count . '</a>';
            if ($nbUnread > 0) {
                Container::get('hooks')->bind('header.toplist', function ($toplists) {
                    $toplists[] = '<li class="reportlink"><span><strong><a href="' .
                        Router::pathFor('Conversations.home', ['inbox_id' => 1]) . '">'
                        . __('Unread messages', 'private_messages') . '</a></strong></span></li>';
                    return $toplists;
                });
            }
        }
        return $navlinks;
    }

    public function install()
    {
        Statical::addNamespace('*', __NAMESPACE__.'\\*');
        Lang::load('private-messages', 'pms', __DIR__ . '/lang');

        $database_scheme = [
            'pms_data' => "CREATE TABLE IF NOT EXISTS %t% (
                `conversation_id` int(10) unsigned NOT NULL,
                `user_id` int(10) unsigned NOT NULL DEFAULT '0',
                `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
                `viewed` tinyint(1) unsigned NOT NULL DEFAULT '0',
                `folder_id` int(10) unsigned NOT NULL DEFAULT '2',
                PRIMARY KEY (`conversation_id`, `user_id`),
                KEY `folder_idx` (`folder_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
            'pms_folders' => "CREATE TABLE IF NOT EXISTS %t% (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(80) NOT NULL DEFAULT 'New Folder',
                `user_id` int(10) unsigned NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                KEY `user_idx` (`user_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
            'pms_messages' => "CREATE TABLE IF NOT EXISTS %t% (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `poster` varchar(200) NOT NULL DEFAULT '',
                `poster_id` int(10) unsigned NOT NULL DEFAULT '1',
                `poster_ip` varchar(39) DEFAULT NULL,
                `message` mediumtext,
                `hide_smilies` tinyint(1) NOT NULL DEFAULT '0',
                `sent` int(10) unsigned NOT NULL DEFAULT '0',
                `edited` int(10) unsigned DEFAULT NULL,
                `edited_by` varchar(200) DEFAULT NULL,
                `conversation_id` int(10) unsigned NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                KEY `conversation_idx` (`conversation_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
            'pms_conversations' => "CREATE TABLE IF NOT EXISTS %t% (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `poster` varchar(200) NOT NULL DEFAULT '',
                `poster_id` int(10) unsigned NOT NULL DEFAULT '0',
                `subject` varchar(255) NOT NULL DEFAULT '',
                `first_post_id` int(10) unsigned NOT NULL DEFAULT '0',
                `last_post_id` int(10) unsigned NOT NULL DEFAULT '0',
                `last_post` int(10) unsigned NOT NULL DEFAULT '0',
                `last_poster` varchar(200) DEFAULT NULL,
                `num_replies` mediumint(8) unsigned NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
            'pms_blocks' => "CREATE TABLE IF NOT EXISTS %t% (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `user_id` int(10) NOT NULL DEFAULT '0',
                `block_id` int(10) NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;"
        ];

        // Create tables
        $installer = new \RunBB\Model\Install();
        foreach ($database_scheme as $table => $sql) {
            $installer->createTable(ForumSettings::get('db_prefix') . $table, $sql);
        }

        \ORM::for_table(ForumSettings::get('db_prefix') . 'groups')->raw_execute('ALTER TABLE ' .
            ForumSettings::get('db_prefix') . 'groups ADD `g_pm_limit` smallint(3) NOT NULL DEFAULT \'0\'');
        \ORM::for_table(ForumSettings::get('db_prefix') . 'groups')->raw_execute('ALTER TABLE ' .
            ForumSettings::get('db_prefix') . 'groups ADD `g_use_pm` tinyint(1) NOT NULL DEFAULT \'0\'');
        \ORM::for_table(ForumSettings::get('db_prefix') . 'groups')->raw_execute('ALTER TABLE ' .
            ForumSettings::get('db_prefix') . 'groups ADD `g_pm_folder_limit` int(3) NOT NULL DEFAULT \'0\'');

        // Create default inboxes
        $folders = [
            d__('pms', 'New'),
            d__('pms', 'Inbox'),
            d__('pms', 'Archived')
        ];

        foreach ($folders as $folder) {
            $insert = [
                'name' => $folder,
                'user_id' => 1,
            ];
            $installer->addData('pms_folders', $insert);
        }
        // copy assets to web dir
        Utils::recurseCopy(
            realpath(__DIR__ . '/../assets'),
            $this->c['forum_env']['WEB_ROOT'] . $this->c['forum_env']['WEB_PLUGINS'].'/'.self::NAME
        );
    }

    public function remove()
    {
        Statical::addNamespace('*', __NAMESPACE__.'\\*');

        $db = \ORM::get_db();
        $tables = ['pms_data', 'pms_folders', 'pms_messages', 'pms_conversations', 'pms_blocks'];
        foreach ($tables as $i) {
            $tableExists = \ORM::for_table(ForumSettings::get('db_prefix') . $i)
                ->raw_query('SHOW TABLES LIKE "' . ForumSettings::get('db_prefix') . $i . '"')
                ->find_one();
            if ($tableExists) {
                $db->exec('DROP TABLE ' . ForumSettings::get('db_prefix') . $i);
            }
        }
        $columns = ['g_pm_limit', 'g_use_pm', 'g_pm_folder_limit'];
        foreach ($columns as $i) {
            $columnExists = \ORM::for_table(ForumSettings::get('db_prefix') . 'groups')
                ->raw_query('SHOW COLUMNS FROM ' . ForumSettings::get('db_prefix') . 'groups LIKE \'' . $i . '\'')
                ->find_one();
            if ($columnExists) {
                $db->exec('ALTER TABLE ' . ForumSettings::get('db_prefix') . 'groups DROP COLUMN ' . $i);
            }
        }

        Utils::recurseDelete(
            $this->c['forum_env']['WEB_ROOT'] . $this->c['forum_env']['WEB_PLUGINS'].'/'.self::NAME
        );
    }
}
