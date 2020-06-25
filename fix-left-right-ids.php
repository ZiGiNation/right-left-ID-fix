<?php
/**
* Use at your own risk! Do a DB backup first!
*.
* This file is released as is, no warraties OF ANY SORT.
*
* -------------------------------------------------------
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
* -------------------------------------------------------
*
* Ported to executable by 3Di in 04/07/2018 - v1.0.0
*/

/**
* @ignore
*/
define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_display.' . $phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);

global $db, $cache;

/**
* Executes the file: fix-left-right-ids.php
*
* Repairs the tree structure of the forums and modules.
* The code is mainly borrowed from Support toolkit for phpBB Olympus
*/

// Fix Left/Right IDs for the modules table
$result = $db->sql_query('SELECT DISTINCT(module_class) FROM ' . MODULES_TABLE);

while ($row = $db->sql_fetchrow($result))
{
	$i = 1;

	$where = array("module_class = '" . $db->sql_escape($row['module_class']) . "'");

	fix_ids_tree($i, 'module_id', MODULES_TABLE, 0, $where);
}
$db->sql_freeresult($result);

// Fix the Left/Right IDs for the forums table
$i = 1;

fix_ids_tree($i, 'forum_id', FORUMS_TABLE);

$cache->purge();

echo 'FIX LEFT RIGHT IDS completed.';

/**
 * Item's tree structure rebuild helper
 * The item is either forum or ACP/MCP/UCP module
 *
 * @param int		$i			Item id offset index
 * @param string	$field		The key field to fix, forum_id|module_id
 * @param string	$table		The table name to perform, FORUMS_TABLE|MODULES_TABLE
 * @param int		$parent_id	Parent item id
 * @param array		$where		Additional WHERE clause condition
 *
 * @return bool	True on rebuild success, false otherwise
 */
function fix_ids_tree(&$i, $field, $table, $parent_id = 0, $where = array())
{
	global $db;

	$changes_made = false;

	$sql = 'SELECT * FROM ' . $table . '
		WHERE parent_id = ' . (int) $parent_id .
		((!empty($where)) ? ' AND ' . implode(' AND ', $where) : '') . '
		ORDER BY left_id ASC';
	$result = $db->sql_query($sql);

	while ($row = $db->sql_fetchrow($result))
	{
		// Update the left_id for the item
		if ($row['left_id'] != $i)
		{
			$db->sql_query('UPDATE ' . $table . ' SET ' . $db->sql_build_array('UPDATE', array('left_id' => $i)) . " WHERE $field = " . (int) $row[$field]);
			$changes_made = true;
		}
		$i++;

		// Go through children and update their left/right IDs
		$changes_made = ((fix_ids_tree($i, $field, $table, $row[$field], $where)) || $changes_made) ? true : false;

		// Update the right_id for the item
		if ($row['right_id'] != $i)
		{
			$db->sql_query('UPDATE ' . $table . ' SET ' . $db->sql_build_array('UPDATE', array('right_id' => $i)) . " WHERE $field = " . (int) $row[$field]);
			$changes_made = true;
		}
		$i++;
	}
	$db->sql_freeresult($result);

	return $changes_made;
}
