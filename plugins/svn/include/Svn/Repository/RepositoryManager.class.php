<?php
/**
 * Copyright (c) Enalean, 2015-2016. All Rights Reserved.
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

namespace Tuleap\Svn\Repository;

use Tuleap\Svn\Dao;
use Project;
use ProjectManager;
use Rule_ProjectName;
use Tuleap\Svn\EventRepository\SystemEvent_SVN_CREATE_REPOSITORY;
use Tuleap\Svn\EventRepository\SystemEvent_SVN_DELETE_REPOSITORY;
use SystemEvent;
use EventManager;
use SystemEventManager;
use Tuleap\Svn\SvnAdmin;
use PluginManager;
use Logger;
use ForgeConfig;
use Tuleap\Svn\Repository\CannotDeleteRepositoryException;
use System_Command;

class RepositoryManager
{
    const PREFIX = "svn_";


    /** @var Dao */
    private $dao;
     /** @var ProjectManager */
    private $project_manager;
     /** @var SvnAdmin */
    private $svnadmin;
     /** @var Logger */
    private $logger;
    /** @var System_Command */
    private $system_command;

    public function __construct(
        Dao $dao,
        ProjectManager $project_manager,
        SvnAdmin $svnadmin,
        Logger $logger,
        System_Command $system_command
    ) {
        $this->dao             = $dao;
        $this->project_manager = $project_manager;
        $this->svnadmin        = $svnadmin;
        $this->logger          = $logger;
        $this->system_command  = $system_command;
    }

    /**
     * @return Repository[]
     */
    public function getRepositoriesInProject(Project $project) {
        $repositories = array();
        foreach ($this->dao->searchByProject($project) as $row) {
            $repositories[] = $this->instantiateFromRow($row, $project);
        }

        return $repositories;
    }

    public function getRepositoryByName(Project $project, $name) {
        $row = $this->dao->searchRepositoryByName($project, $name);
        if ($row) {
            return $this->instantiateFromRow($row, $project);
        } else {
            throw new CannotFindRepositoryException();
        }
    }

    public function getById($id_repository, Project $project) {
        $row = $this->dao->searchByRepositoryIdAndProjectId($id_repository, $project);
        if (! $row) {
            throw new CannotFindRepositoryException();
        }

        return $this->instantiateFromRow($row, $project);
    }

    /**
     * @return SystemEvent or null
     */
    public function create(Repository $repositorysvn, \SystemEventManager $system_event_manager) {
        $id = $this->dao->create($repositorysvn);
        if (! $id) {
            throw new CannotCreateRepositoryException ($GLOBALS['Language']->getText('plugin_svn','update_error'));
        }
        $repositorysvn->setId($id);

        $repo_event['system_path'] = $repositorysvn->getSystemPath();
        $repo_event['project_id']  = $repositorysvn->getProject()->getId();
        $repo_event['name']        = $repositorysvn->getProject()->getUnixNameMixedCase()."/".$repositorysvn->getName();
        return $system_event_manager->createEvent(
            'Tuleap\\Svn\\EventRepository\\'.SystemEvent_SVN_CREATE_REPOSITORY::NAME,
            implode(SystemEvent::PARAMETER_SEPARATOR, $repo_event),
            SystemEvent::PRIORITY_HIGH);
    }

    public function delete(Repository $repository)
    {
        $project = $repository->getProject();
        if (! $project) {
            return false;
        }

        $system_path = $repository->getSystemPath();
        if (is_dir($system_path)) {
            return $this->system_command->exec('rm -rf '. escapeshellarg($system_path));
        }

        return false;
    }

    /**
     * @return SystemEvent or null
     */
    public function queueRepositoryDeletion(Repository $repositorysvn, \SystemEventManager $system_event_manager)
    {
        return $system_event_manager->createEvent(
            'Tuleap\\Svn\\EventRepository\\'.SystemEvent_SVN_DELETE_REPOSITORY::NAME,
            $repositorysvn->getProject()->getID() . SystemEvent::PARAMETER_SEPARATOR . $repositorysvn->getId(),
            SystemEvent::PRIORITY_MEDIUM,
            SystemEvent::OWNER_ROOT
        );
    }

    public function getRepositoryFromSystemPath($path) {
         if (! preg_match('/\/(\d+)\/('.RuleName::PATTERN_REPOSITORY_NAME.')$/', $path, $matches)) {
            throw new CannotFindRepositoryException($GLOBALS['Language']->getText('plugin_svn','find_error'));
        }

        $project = $this->project_manager->getProject($matches[1]);
        return $this->getRepositoryIfProjectIsValid($project, $matches[2]);
    }

    public function getRepositoryFromPublicPath($path) {
         if (! preg_match('/^('.Rule_ProjectName::PATTERN_PROJECT_NAME.')\/('.RuleName::PATTERN_REPOSITORY_NAME.')$/', $path, $matches)) {
            throw new CannotFindRepositoryException();
        }

        $project = $this->project_manager->getProjectByUnixName($matches[1]);

        return $this->getRepositoryIfProjectIsValid($project, $matches[2]);
    }

    private function getRepositoryIfProjectIsValid($project, $repository_name) {
        if (!$project instanceof Project || $project->getID() == null || $project->isError()) {
            throw new CannotFindRepositoryException($GLOBALS['Language']->getText('plugin_svn','find_error'));
        }

        return $this->getRepositoryByName($project, $repository_name);
    }

    /**
     * @return Repository
     */
    public function instantiateFromRow(array $row, Project $project)
    {
        return new Repository(
            $row['id'],
            $row['name'],
            $row['repository_deletion_date'],
            $row['backup_path'],
            $project
        );
    }

    public function getHookConfig(Repository $repository) {
        $row = $this->dao->getHookConfig($repository->getId());
        if(!$row) {
            $row = array();
        }
        return new HookConfig($repository, $row);
    }

    public function updateHookConfig($repository_id, array $hook_config) {
        return $this->dao->updateHookConfig(
            $repository_id,
            HookConfig::sanitizeHookConfigArray($hook_config)
        );
    }

    public function markAsDeleted(Repository $repository)
    {
        if ($repository->canBeDeleted()) {
            $deletion_date = time();
            $repository->setDeletionDate($deletion_date);
            $this->dao->markAsDeleted(
                $repository->getId(),
                $repository->getSystemBackupPath() . "/" . $repository->getBackupFileName(),
                $deletion_date
            );
        } else {
            throw new CannotDeleteRepositoryException($GLOBALS['Language']->getText('plugin_svn', 'delete_repository_exception'));
        }
    }

    public function dumpRepository(Repository $repository)
    {
        return $this->svnadmin->dumpRepository($repository);
    }
}
