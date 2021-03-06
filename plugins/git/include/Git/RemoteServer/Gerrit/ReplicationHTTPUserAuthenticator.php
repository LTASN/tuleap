<?php
/**
 * Copyright (c) Enalean, 2016. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Tuleap\Git\Gerrit;

use Git_RemoteServer_GerritServer;
use Git_RemoteServer_GerritServerFactory;
use PasswordHandler;
use User_InvalidPasswordException;
use Rule_UserName;

class ReplicationHTTPUserAuthenticator
{

    /**
     * @var Git_RemoteServer_GerritServerFactory
     */
    private $server_factory;

    /**
     * @var PasswordHandler
     */
    private $password_handler;

    public function __construct(PasswordHandler $password_handler, Git_RemoteServer_GerritServerFactory $server_factory)
    {
        $this->password_handler = $password_handler;
        $this->server_factory   = $server_factory;
    }

    /**
     * @throws User_InvalidPasswordException
     */
    public function authenticate(Git_RemoteServer_GerritServer $gerrit_server, $login, $password)
    {
        if (! $this->isLoginAnHTTPUserLogin($login)) {
            return;
        }

        if (hash_equals($gerrit_server->getGenericUserName(), $login) &&
            $this->password_handler->verifyHashPassword($password, $gerrit_server->getReplicationPassword())
        ) {
            $this->checkPasswordStorageConformity($gerrit_server, $password);
            return new ReplicationHTTPUser($gerrit_server);
        }

        throw new User_InvalidPasswordException();
    }

    private function isLoginAnHTTPUserLogin($login)
    {
        $pattern = '/^'.Rule_UserName::RESERVED_PREFIX.Git_RemoteServer_GerritServer::GENERIC_USER_PREFIX.'[0-9]+$/';

        return preg_match($pattern, $login);
    }

    private function checkPasswordStorageConformity(Git_RemoteServer_GerritServer $gerrit_server, $password)
    {
        if ($this->password_handler->isPasswordNeedRehash($gerrit_server->getReplicationPassword())) {
            $gerrit_server->setReplicationPassword($password);
            $this->server_factory->updateReplicationPassword($gerrit_server);
        }
    }
}
