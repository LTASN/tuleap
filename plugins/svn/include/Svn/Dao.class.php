<?php
/**
  * Copyright (c) Enalean, 2016. All rights reserved
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
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  * GNU General Public License for more details.
  *
  * You should have received a copy of the GNU General Public License
  * along with Tuleap. If not, see <http://www.gnu.org/licenses/
  */

namespace Tuleap\Svn;

use DataAccessObject;
use Tuleap\Svn\Repository\Repository;
use Project;
use SVN_Apache_SvnrootConf;
use ForgeConfig;

class Dao extends DataAccessObject
{
    public function searchByProject(Project $project)
    {
        $project_id = $this->da->escapeInt($project->getId());
        $sql = 'SELECT *
                FROM plugin_svn_repositories
                WHERE project_id=' . $project_id .'
                AND repository_deletion_date IS NULL';

        return $this->retrieve($sql);
    }

    public function searchByRepositoryIdAndProjectId($id, Project $project) {
        $id         = $this->da->escapeInt($id);
        $project_id = $this->da->escapeInt($project->getId());
        $sql = "SELECT *
                FROM plugin_svn_repositories
                WHERE id=$id AND project_id=$project_id";

        return $this->retrieveFirstRow($sql);
    }

    public function doesRepositoryAlreadyExist($name, Project $project) {
        $name       = $this->da->quoteSmart($name);
        $project_id = $this->da->escapeInt($project->getId());
        $sql = "SELECT *
                FROM plugin_svn_repositories
                WHERE name=$name AND project_id=$project_id
                LIMIT 1";

        return count($this->retrieve($sql)) > 0;
    }

    public function getListRepositoriesSqlFragment()
    {
        $auth_mod = $this->da->quoteSmart(SVN_Apache_SvnrootConf::CONFIG_SVN_AUTH_PERL);
        $sys_dir  = $this->da->quoteSmart(ForgeConfig::get('sys_data_dir'));

        $sql = "SELECT groups.*, service.*,
                CONCAT('/svnplugin/', unix_group_name, '/', name) AS public_path,
                CONCAT($sys_dir,'/svn_plugin/', groups.group_id, '/', name) AS system_path,
                $auth_mod AS auth_mod
                FROM groups, service, plugin_svn_repositories
                WHERE groups.group_id = service.group_id
                  AND service.is_used = '1'
                  AND groups.status = 'A'
                  AND plugin_svn_repositories.project_id = groups.group_id
                  AND service.short_name = 'plugin_svn'
                  AND repository_deletion_date IS NOT NULL";

        return $sql;
    }

    public function searchRepositoryByName(Project $project, $name) {
        $project_name = $this->da->quoteSmart($project->getUnixNameMixedCase());
        $name         = $this->da->quoteSmart($name);

        $sql = "SELECT groups.*, id, name, CONCAT(unix_group_name, '/', name) AS repository_name
                FROM groups, plugin_svn_repositories
                WHERE groups.status = 'A' AND project_id = groups.group_id
                AND groups.unix_group_name = $project_name
                AND plugin_svn_repositories.name = $name";

        return $this->retrieveFirstRow($sql);
    }

     public function create(Repository $repository) {
        $name       = $this->da->quoteSmart($repository->getName());
        $project_id = $this->da->escapeInt($repository->getProject()->getId());

        $query = "INSERT INTO plugin_svn_repositories
            (name,  project_id ) values ($name, $project_id)";

        return $this->updateAndGetLastId($query);
    }

    public function getHookConfig($id_repository) {
        $id_repository = $this->da->escapeInt($id_repository);
        $sql = "SELECT *
                FROM plugin_svn_hook_config
                WHERE repository_id = $id_repository";
        return $this->retrieveFirstRow($sql);
    }

    public function updateHookConfig($id_repository, array $hook_config) {
        $id         = $this->da->escapeInt($id_repository);

        $update = array();
        $cols = array();
        $vals = array();
        foreach($hook_config as $tablename => $value) {
            $update[] = "$tablename = " . $this->da->quoteSmart((bool) $value);
            $cols[] = $tablename;
            $vals[] = $this->da->quoteSmart((bool) $value);
        }

        $sql  = "INSERT INTO plugin_svn_hook_config";
        $sql .= " (repository_id, ". join($cols, ", ") . ")";
        $sql .= " VALUES ($id, " . join($vals, ", ") . ")";
        $sql .= " ON DUPLICATE KEY UPDATE " . join($update, ", ") . ";";

        return $this->update($sql);
    }

    public function markAsDeleted($repository_id, $backup_path, $deletion_date)
    {
        $backup_path   = $this->da->quoteSmart($backup_path);
        $repository_id = $this->da->escapeInt($repository_id);
        $deletion_date = $this->da->quoteSmart($deletion_date);

        $sql = "UPDATE plugin_svn_repositories SET
                    repository_deletion_date = $deletion_date,
                    backup_path = $backup_path
                WHERE id = $repository_id";

        return $this->update($sql);
    }
}
