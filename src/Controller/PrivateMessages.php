<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2012 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunPMS\Controller;

use RunBB\Exception\RunBBException;
use RunBB\Core\Url;
use RunBB\Core\Utils;

class PrivateMessages
{
    protected $model;
    protected $crumbs;
    protected $inboxes;

    public function __construct()
    {
        $this->model = new \RunPMS\Model\PrivateMessages();

        Lang::load('private-messages', 'pms', __DIR__ . '/../lang');
        View::addTemplatesDirectory(dirname(dirname(__FILE__)) . '/Views', 'pms')
            ->setPageInfo(['active_page' => 'navextra1']);
        $this->crumbs = [
            Router::pathFor('Conversations.home') => d__('pms', 'PMS')
        ];
    }

    public function index($req, $res, $args)
    {
        // Set default page to "Inbox" folder
        if (!isset($args['inbox_id'])) {
            $args['inbox_id'] = 2;
        }

        if (!isset($args['page'])) {
            $args['page'] = 1;
        }

        $uid = intval(User::get()->id);

        if ($action = Input::post('action')) {
            switch ($action) {
                case 'move':
                    $this->move($req, $res, $args);
                    break;
                case 'delete':
                    $this->delete($req, $res, $args);
                    break;
                case 'read':
                    $this->markRead($req, $res, $args);
                    break;
                case 'unread':
                    $this->markRead($req, $res, $args);
                    break;
                default:
                    return Router::redirect(Router::pathFor(
                        'Conversations.home',
                        ['inbox_id' => Input::post('inbox_id')]
                    ));
                    break;
            }
        }

        if ($this->inboxes = $this->model->getInboxes(User::get()->id)) {
            if (!in_array($args['inbox_id'], array_keys($this->inboxes))) {
                throw new RunBBException(d__('pms', 'Wrong folder owner'), 403);
            }
        }
        // Page data
        $num_pages = ceil($this->inboxes[$args['inbox_id']]['nb_msg'] / User::get()['disp_topics']);
        $p = (!isset($args['page']) || $args['page'] <= 1 || $args['page'] > $num_pages) ? 1 : intval($args['page']);
        $start_from = User::get()['disp_topics'] * ($p - 1);
        $paging_links = Url::paginate(
            $num_pages,
            $p,
            Router::pathFor('Conversations.home', ['id' => $args['inbox_id']]) . '/#'
        );

        // Make breadcrumbs
        $this->crumbs[Router::pathFor(
            'Conversations.home',
            ['inbox_id' => $args['inbox_id']]
        )] = $this->inboxes[$args['inbox_id']]['name'];
        $this->crumbs[] = d__('pms', 'My conversations');
        Utils::generateBreadcrumbs($this->crumbs, [
            'link' => Router::pathFor('Conversations.send'),
            'text' => d__('pms', 'Send')
        ]);

        $this->generateMenu($this->inboxes[$args['inbox_id']]['name']);
        View::addAsset('js', 'assets/js/common.js', ['type' => 'text/javascript']);
        return View::setPageInfo([
            'title' => [
                Utils::escape(ForumSettings::get('o_board_title')),
                d__('pms', 'PMS'),
                $this->inboxes[$args['inbox_id']]['name']
            ],
            'admin_console' => true,
            'inboxes' => $this->inboxes,
            'current_inbox_id' => $args['inbox_id'],
            'paging_links' => $paging_links,
            'rightLink' => [
                'link' => Router::pathFor('Conversations.send'),
                'text' => d__('pms', 'Send')
            ],
            'conversations' => $this->model->getConversations(
                $args['inbox_id'],
                $uid,
                User::get()['disp_topics'],
                $start_from
            )
        ])->display('@pms/index');
    }


    public function info($req, $res, $args)
    {
        Lang::load('admin-permissions');
        Lang::load('install');// groups name? why only in install?

        // Update permissions
        if (Request::isPost()) {
            return $this->model->updatePermissions();
        }
        return View::setPageInfo([
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), d__('pms', 'PMS')],
            'groups' => $this->model->fetchGroups(),
            'admin_console' => true,
        ])->display('@pms/info');
    }

    public function delete($req, $res, $args)
    {
        if (!Input::post('topics')) {
            throw new RunBBException(d__('pms', 'No conv selected'), 403);
        }

        $topics = Input::post('topics') && is_array(Input::post('topics')) ?
            array_map('intval', Input::post('topics')) : array_map('intval', explode(',', Input::post('topics')));

        if (empty($topics)) {
            throw new RunBBException(d__('pms', 'No conv selected'), 403);
        }

        if (Input::post('delete_comply')) {
            $uid = intval(User::get()->id);
            $this->model->delete($topics, $uid);

            return Router::redirect(
                Router::pathFor('Conversations.home'),
                d__('pms', 'Conversations deleted')
            );
        } else {
            // Display confirm delete form
            return View::setPageInfo([
                'title' => [
                    Utils::escape(ForumSettings::get('o_board_title')),
                    d__('pms', 'PMS')
                ],
                'topics' => $topics,
            ])->display('@pms/delete');
        }
    }

    public function move($req, $res, $args)
    {
        if (!Input::post('topics')) {
            throw new RunBBException(d__('pms', 'No conv selected'), 403);
        }

        $topics = Input::post('topics') && is_array(Input::post('topics')) ?
            array_map('intval', Input::post('topics')) : array_map('intval', explode(',', Input::post('topics')));

        if (empty($topics)) {
            throw new RunBBException(d__('pms', 'No conv selected'), 403);
        }

        $uid = intval(User::get()->id);

        if (Input::post('move_comply')) {
            $move_to = Input::post('move_to') ? intval(Input::post('move_to')) : 2;

            if ($this->model->move($topics, $move_to, $uid)) {
                return Router::redirect(
                    Router::pathFor('Conversations.home', ['inbox_id' => $move_to]),
                    d__('pms', 'Conversations moved')
                );
            } else {
                throw new RunBBException(d__('pms', 'Error Move'), 403);
            }
        }

        // Display move form
        if ($inboxes = $this->model->getUserFolders($uid)) {
            View::setPageInfo([
                'title' => [
                    Utils::escape(ForumSettings::get('o_board_title')),
                    d__('pms', 'PMS')
                ],
                'topics' => $topics,
                'inboxes' => $inboxes,
            ])->display('@pms/move');
        } else {
            throw new RunBBException('No inboxes', 404);
        }
    }

    public function markRead($req, $res, $args)
    {
        $read = false;

        if (isset($args['read'])) {
            $read = true;
        }

        $viewed = ($read == true) ? '1' : '0';

        if (!Input::post('topics')) {
            throw new RunBBException(d__('pms', 'No conv selected'), 403);
        }

        $topics = Input::post('topics') && is_array(Input::post('topics')) ?
            array_map('intval', Input::post('topics')) : array_map('intval', explode(',', Input::post('topics')));

        if (empty($topics)) {
            throw new RunBBException(d__('pms', 'No conv selected'), 403);
        }

        $this->model->updateConversation($topics, User::get()->id, ['viewed' => $viewed]);

        return Router::redirect(Router::pathFor('Conversations.home', ['inbox_id' => Input::post('inbox_id')]));
    }

    public function send($req, $res, $args)
    {
        if (!isset($args['uid'])) {
            $args['uid'] = null;
        }

        if (!isset($args['tid'])) {
            $args['tid'] = null;
        }

        $this->generateMenu('folders');

        if (Request::isPost()) {
            // First raw validation
            $data = array_merge([
                'username' => null,
                'subject' => null,
                'message' => null,
                'smilies' => 0,
                'preview' => null,
            ], Request::getParsedBody());
            $data = array_map(['RunBB\Core\Utils', 'trim'], $data);

            $conv = false;

            if (!is_null($args['tid'])) {
                if ($args['tid'] < 1) {
                    throw new RunBBException('Wrong conversation ID', 400);
                }
                if (!$conv = $this->model->getConversation($args['tid'], User::get()->id)) {
                    throw new RunBBException('Unknown conversation ID', 400);
                }
            }

            // Preview message
            if (Input::post('preview')) {
                // Make breadcrumbs
                $this->crumbs[] = d__('pms', 'Reply');
                $this->crumbs[] = __('Preview');
                Utils::generateBreadcrumbs($this->crumbs);

                Container::get('hooks')->fire('conversationsPlugin.send.preview');
                $msg = Container::get('parser')->parse_message($data['req_message'], $data['smilies']);
                View::setPageInfo([
                    'parsed_message' => $msg,
                    'username' => Utils::escape($data['username']),
                    'subject' => Utils::escape($data['subject']),
                    'message' => Utils::escape($data['req_message'])
                ])->display('@pms/send');
            } else {
                // Prevent flood
                if (!is_null($data['preview']) && User::get()['last_post'] != '' &&
                    (Container::get('now') - User::get()['last_post']) < Container::get('prefs')->get(
                        User::get(),
                        'post.min_interval'
                    )
                ) {
                    throw new RunBBException(sprintf(__('Flood start'),
                        Container::get('prefs')->get(User::get(), 'post.min_interval'),
                        Container::get('prefs')->get(User::get(), 'post.min_interval') -
                        (Container::get('now') - User::get()['last_post'])), 429);
                }

                if (!$conv) {
                    // Validate username / TODO : allow multiple usernames
                    if (!$user = $this->model->isAllowed($data['username'])) {
                        throw new RunBBException('You can\'t send an PM to ' .
                            ($data['username'] ? $data['username'] : 'nobody'), 400);
                    }

                    // Avoid self messages
                    if ($user->id == User::get()->id) {
                        throw new RunBBException('No self message', 403);
                    }

                    // Validate subject
                    if (ForumSettings::get('o_censoring') == '1') {
                        $data['subject'] = Utils::trim(Utils::censor($data['subject']));
                    }
                    if (empty($data['subject'])) {
                        throw new RunBBException('No subject or censored subject', 400);
                    } elseif (Utils::strlen($data['subject']) > 70) {
                        throw new RunBBException('Too long subject', 400);
                    } elseif (ForumSettings::get('p_subject_all_caps') == '0' &&
                        Utils::isAllUppercase($data['subject']) && !User::get()->is_admmod
                    ) {
                        throw new RunBBException('All caps subject forbidden', 400);
                    }
                }

                // TODO : inbox full

                // Validate message
                if (ForumSettings::get('o_censoring') == '1') {
                    $data['req_message'] = Utils::trim(Utils::censor($data['req_message']));
                }
                if (empty($data['req_message'])) {
                    throw new RunBBException('No message or censored message', 400);
                } elseif (Utils::strlen($data['req_message']) > ForumEnv::get('FEATHER_MAX_POSTSIZE')) {
                    throw new RunBBException('Too long message', 400);
                } elseif (ForumSettings::get('p_subject_all_caps') == '0' &&
                    Utils::isAllUppercase($data['subject']) && !User::get()->is_admmod
                ) {
                    throw new RunBBException('All caps message forbidden', 400);
                }

                // Send ... TODO : when perms will be ready
                // Check if the receiver has the PM enabled
                // Check if he has reached his max limit of PM
                // Block feature ?

                if (!$conv) {
                    $conv_data = [
                        'subject' => $data['subject'],
                        'poster' => User::get()->username,
                        'poster_id' => User::get()->id,
                        'num_replies' => 0,
                        'last_post' => Container::get('now'),
                        'last_poster' => User::get()->username
                    ];
                    $args['tid'] = $this->model->addConversation($conv_data);
                }
                if ($args['tid']) {
                    $msg_data = [
                        'poster' => User::get()->username,
                        'poster_id' => User::get()->id,
                        'poster_ip' => Utils::getIp(),
                        'message' => $data['req_message'],
                        'hide_smilies' => $data['smilies'],
                        'sent' => Container::get('now'),
                    ];
                    if ($conv) {
                        // Reply to an existing conversation
                        if ($msg_id = $this->model->addMessage($msg_data, $args['tid'])) {
                            return Router::redirect(
                                Router::pathFor('Conversations.home'),
                                sprintf(d__('pms', 'Reply success'), $conv->subject)
                            );
                        }
                    } else {
                        // Add message in conversation + add receiver (create new conversation)
                        if ($msg_id = $this->model->addMessage(
                            $msg_data,
                            $args['tid'],
                            [$user->id, User::get()->id]
                        )
                        ) {
                            return Router::redirect(
                                Router::pathFor('Conversations.home'),
                                sprintf(d__('pms', 'Send success'), $user->username)
                            );
                        }
                    }
                } else {
                    throw new RunBBException('Unable to create conversation');
                }
            }
        } else {
            Container::get('hooks')->fire('conversationsPlugin.send.display');
            // New conversation
            if (!is_null($args['uid'])) {
                if ($args['uid'] < 2) {
                    throw new RunBBException('Wrong user ID', 400);
                }
                if ($user = $this->model->getUserByID($args['uid'])) {
                    View::setPageInfo(['username' => Utils::escape($user->username)]);
                } else {
                    throw new RunBBException('Unable to find user', 400);
                }
            }
            // Reply
            if (!is_null($args['tid'])) {
                if ($args['tid'] < 1) {
                    throw new RunBBException('Wrong conversation ID', 400);
                }
                if ($conv = $this->model->getConversation($args['tid'], User::get()->id)) {
                    $inbox = \ORM::for_table(ORM_TABLE_PREFIX . 'pms_folders')->find_one($conv->folder_id);
                    $this->crumbs[Router::pathFor('Conversations.home', ['inbox_id' => $inbox['id']])] = $inbox['name'];
                    $this->crumbs[] = d__('pms', 'Reply');
                    $this->crumbs[] = $conv['subject'];
                    Utils::generateBreadcrumbs($this->crumbs);
                    return View::setPageInfo([
                        'current_inbox' => $inbox,
                        'conv' => $conv,
                        'msg_data' => $this->model->getMessagesFromConversation($args['tid'], User::get()->id, 5)
                    ])->display('@pms/reply');
                } else {
                    throw new RunBBException('Unknown conversation ID', 400);
                }
            }
            $this->crumbs[] = d__('pms', 'Send');
            if (isset($user)) {
                $this->crumbs[] = $user->username;
            }
            Utils::generateBreadcrumbs($this->crumbs);
//            return View::addTemplate('@pms/send')->display();

            return View::setPageInfo([
                'title' => [
                    Utils::escape(ForumSettings::get('o_board_title')),
                    d__('pms', 'PMS'), d__('pms', 'Send')
                ],
            ])->display('@pms/send');
        }
    }

    public function reply($req, $res, $args)
    {
        return $this->send($req, $res, $args);
    }

    public function show($req, $res, $args)
    {
        if (!isset($args['tid'])) {
            $args['tid'] = null;
        }

        if (!isset($args['page'])) {
            $args['page'] = null;
        }

        // First checks
        if ($args['tid'] < 1) {
            throw new RunBBException('Wrong conversation ID', 400);
        }
        if (!$conv = $this->model->getConversation($args['tid'], User::get()->id)) {
            throw new RunBBException('Unknown conversation ID', 404);
        } elseif ($this->model->isDeleted($args['tid'], User::get()->id)) {
            throw new RunBBException('The conversation has been deleted', 404);
        }

        // Set conversation as viewed
        if ($conv['viewed'] == 0) {
            if (!$this->model->setViewed($args['tid'], User::get()->id)) {
                throw new RunBBException('Unable to set conversation as viewed', 500);
            }
        }

        $num_pages = ceil($conv['num_replies'] / User::get()['disp_topics']);
        $p = (!is_null($args['page']) || $args['page'] <= 1 || $args['page'] > $num_pages) ? 1 : intval($args['page']);
        $start_from = User::get()['disp_topics'] * ($p - 1);
        $paging_links = Url::paginate(
            $num_pages,
            $p,
            Router::pathFor('Conversations.show', ['tid' => $args['tid']]) . '/#'
        );

        $this->inboxes = $this->model->getInboxes(User::get()->id);

        $this->crumbs[Router::pathFor('Conversations.home', ['inbox_id' => $conv['folder_id']])] = $this->inboxes[$conv['folder_id']]['name'];
        $this->crumbs[] = d__('pms', 'My conversations');
        $this->crumbs[] = $conv['subject'];
        Utils::generateBreadcrumbs($this->crumbs, [
            'link' => Router::pathFor('Conversations.reply', ['tid' => $conv['id']]),
            'text' => d__('pms', 'Reply')
        ]);
        $this->generateMenu($this->inboxes[$conv['folder_id']]['name']);
        return View::setPageInfo([
            'title' => [
                Utils::escape(ForumSettings::get('o_board_title')),
                d__('pms', 'PMS'),
                $this->model->getUserFolders(User::get()->id)[$conv['folder_id']]['name'],
                Utils::escape($conv['subject'])
            ],
            'admin_console' => true,
            'paging_links' => $paging_links,
            'start_from' => $start_from,
            'cur_conv' => $conv,
            'rightLink' => [
                'link' => Router::pathFor(
                    'Conversations.reply',
                    ['tid' => $conv['id']]
                ),
                'text' => d__('pms', 'Reply')
            ],
            'messages' => $this->model->getMessages($conv['id'], User::get()['disp_topics'], $start_from)
        ])->display('@pms/show');
    }

    public function blocked($req, $res, $args)
    {
        $errors = [];

        $username = Input::post('req_username') ? Utils::trim(Utils::escape(Input::post('req_username'))) : '';
        if (Input::post('add_block')) {
            if ($username == User::get()->username) {
                $errors[] = d__('pms', 'No block self');
            }

            if (!($user_infos = $this->model->getUserByName($username)) || $username == __('Guest')) {
                $errors[] = sprintf(d__('pms', 'No user name message'), Utils::escape($username));
            }

            if (empty($errors)) {
                if ($user_infos->group_id == ForumEnv::get('FEATHER_ADMIN')) {
                    $errors[] = sprintf(d__('pms', 'User is admin'), Utils::escape($username));
                } elseif ($user_infos->group_id == ForumEnv::get('FEATHER_MOD')) {
                    $errors[] = sprintf(d__('pms', 'User is mod'), Utils::escape($username));
                }

                if ($this->model->checkBlock(User::get()->id, $user_infos->id)) {
                    $errors[] = sprintf(d__('pms', 'Already blocked'), Utils::escape($username));
                }
            }

            if (empty($errors)) {
                $insert = [
                    'user_id' => User::get()->id,
                    'block_id' => $user_infos->id,
                ];

                $this->model->addBlock($insert);
                return Router::redirect(
                    Router::pathFor('Conversations.blocked'),
                    d__('pms', 'Block added')
                );
            }
        } elseif (Input::post('remove_block')) {
            $id = intval(key(Input::post('remove_block')));
            // Before we do anything, check we blocked this user
            if (!$this->model->checkBlock(intval(User::get()->id), $id)) {
                throw new RunBBException(__('No permission'), 403);
            }

            $this->model->removeBlock(intval(User::get()->id), $id);
            return Router::redirect(
                Router::pathFor('Conversations.blocked'),
                d__('pms', 'Block removed')
            );
        }

        Utils::generateBreadcrumbs([
            Router::pathFor('Conversations.home') => d__('pms', 'PMS'),
            __('Options'),
            d__('pms', 'Blocked Users')
        ]);

        $this->generateMenu('blocked');
        return View::setPageInfo([
            'title' => [
                Utils::escape(ForumSettings::get('o_board_title')),
                d__('pms', 'PMS'), d__('pms', 'Blocked Users')
            ],
            'admin_console' => true,
            'errors' => $errors,
            'username' => $username,
            'required_fields' => ['req_username' => d__('pms', 'Add block')],
            'blocks' => $this->model->getBlocked(User::get()->id),
        ])->display('@pms/blocked');
    }

    public function folders($req, $res, $args)
    {
        $errors = [];

        if (Input::post('add_folder')) {
            $folder = Input::post('req_folder') ? Utils::trim(Utils::escape(Input::post('req_folder'))) : '';

            if ($folder == '') {
                $errors[] = d__('pms', 'No folder name');
            } elseif (Utils::strlen($folder) < 4) {
                $errors[] = d__('pms', 'Folder too short');
            } elseif (Utils::strlen($folder) > 30) {
                $errors[] = d__('pms', 'Folder too long');
            } elseif (ForumSettings::get('o_censoring') == '1' && Utils::censor($folder) == '') {
                $errors[] = d__('pms', 'No folder after censoring');
            }

            // TODO: Check perms when ready
            // $data = array(
            //     ':uid'    =>    $panther_user['id'],
            // );
            //
            // if ($panther_user['g_pm_folder_limit'] != 0)
            // {
            //     $ps = $db->select('folders', 'COUNT(id)', $data, 'user_id=:uid');
            //     $num_folders = $ps->fetchColumn();
            //
            //     if ($num_folders >= $panther_user['g_pm_folder_limit'])
            //         $errors[] = sprintf($lang_pm['Folder limit'], $panther_user['g_pm_folder_limit']);
            // }

            if (empty($errors)) {
                $insert = [
                    'user_id' => User::get()->id,
                    'name' => $folder
                ];

                $this->model->addFolder($insert);
                return Router::redirect(
                    Router::pathFor('Conversations.folders'),
                    d__('pms', 'Folder added')
                );
            }
        } elseif (Input::post('update_folder')) {
            $id = intval(key(Input::post('update_folder')));
            var_dump($id);

            $errors = [];
            $folder = Utils::trim(Input::post('folder')[$id]);

            if ($folder == '') {
                $errors[] = d__('pms', 'No folder name');
            } elseif (Utils::strlen($folder) < 4) {
                $errors[] = d__('pms', 'Folder too short');
            } elseif (Utils::strlen($folder) > 30) {
                $errors[] = d__('pms', 'Folder too long');
            } elseif (ForumSettings::get('o_censoring') == '1' && Utils::censor($folder) == '') {
                $errors[] = d__('pms', 'No folder after censoring');
            }

            if (empty($errors)) {
                $update = [
                    'name' => $folder,
                ];

                if ($this->model->updateFolder(User::get()->id, $id, $update)) {
                    return Router::redirect(
                        Router::pathFor('Conversations.folders'),
                        d__('pms', 'Folder updated')
                    );
                } else {
                    throw new RunBBException(__('Error'), 403);
                }
            }
        } elseif (Input::post('remove_folder')) {
            $id = intval(key(Input::post('remove_folder')));
            // Before we do anything, check we blocked this user
            if (!$this->model->checkFolderOwner($id, User::get()->id)) {
                throw new RunBBException(__('No permission'), 403);
            }

            if ($this->model->removeFolder(User::get()->id, $id)) {
                return Router::redirect(
                    Router::pathFor('Conversations.folders'),
                    d__('pms', 'Folder removed')
                );
            } else {
                throw new RunBBException(__('Error'), 403);
            }
        }

        Utils::generateBreadcrumbs([
            Router::pathFor('Conversations.home') => d__('pms', 'PMS'),
            __('Options'),
            d__('pms', 'My Folders')
        ]);
        $this->generateMenu('folders');
        return View::setPageInfo([
            'title' => [
                Utils::escape(ForumSettings::get('o_board_title')),
                d__('pms', 'PMS'),
                d__('pms', 'Blocked Users')
            ],
            'admin_console' => true,
            'errors' => $errors,
            'folders' => array_diff_key($this->inboxes, [1, 2, 3, 4])
        ])->display('@pms/folders');
    }

    public function generateMenu($page = '')
    {
        if (!isset($this->inboxes)) {
            $this->inboxes = $this->model->getInboxes(User::get()->id);
        }

        View::setPageInfo([
            'page' => $page,
            'inboxes' => $this->inboxes,
        ], 1);//->addTemplate('menu');
        return $this->inboxes;
    }
}
