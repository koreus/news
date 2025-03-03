<?php declare(strict_types=1);

namespace XoopsModules\News;

/**
 * XOOPS tree handler
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright       XOOPS Project (https://xoops.org)
 * @license         GNU GPL 2 (https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)
 * @since           2.0.0
 * @author          Kazumi Ono (AKA onokazu) http://www.myweb.ne.jp/, http://jp.xoops.org/
 */

/**
 * Abstract base class for forms
 *
 * @author     Kazumi Ono <onokazu@xoops.org>
 * @author     John Neill <catzwolf@xoops.org>
 * @copyright  copyright (c) XOOPS.org
 */
class XoopsTree
{
    public                $table; //table with parent-child structure
    public                $id; //name of unique id for records in table $table
    public                $pid; // name of parent id used in table $table
    public                $order; //specifies the order of query results
    public                $title; // name of a field in table $table which will be used when  selection box and paths are generated
    public \XoopsDatabase $db;
    //constructor of class XoopsTree
    //sets the names of table, unique id, and parend id

    /**
     * @param $table_name
     * @param $id_name
     * @param $pid_name
     */
    public function __construct($table_name, $id_name, $pid_name)
    {
//        $trace = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 1);
//        $GLOBALS['xoopsLogger']->addDeprecated("Class '" . __CLASS__ . "' is deprecated, check 'XoopsObjectTree' in tree.php" . ". Called from {$trace[0]['file']} line {$trace[0]['line']}");
        /** @var \XoopsMySQLDatabase $db */
        $this->db    = \XoopsDatabaseFactory::getDatabaseConnection();
        $this->table = $table_name;
        $this->id    = $id_name;
        $this->pid   = $pid_name;
    }

    // returns an array of first child objects for a given id($sel_id)

    /**
     * @param        $sel_id
     * @param string $order
     *
     * @return array
     */
    public function getFirstChild($sel_id, string $order = ''): array
    {
        $sel_id = (int)$sel_id;
        $arr    = [];
        $sql    = 'SELECT * FROM ' . $this->table . ' WHERE ' . $this->pid . '=' . $sel_id;
        if ('' !== $order) {
            $sql .= " ORDER BY $order";
        }
        $result = Utility::queryAndCheck($this->db, $sql);
        if ($this->db->isResultSet($result)) {
            while (false !== ($myrow = $this->db->fetchArray($result))) {
                $arr[] = $myrow;
            }
        }

        return $arr;
    }

    // returns an array of all FIRST child ids of a given id($sel_id)

    /**
     * @param $sel_id
     *
     * @return array
     */
    public function getFirstChildId($sel_id): array
    {
        $sel_id  = (int)$sel_id;
        $idarray = [];
        $sql     = 'SELECT ' . $this->id . ' FROM ' . $this->table . ' WHERE ' . $this->pid . '=' . $sel_id;
        $result  = Utility::queryAndCheck($this->db, $sql);
        $count   = $this->db->getRowsNum($result);
        if (0 == $count) {
            return $idarray;
        }
        while ([$id] = $this->db->fetchRow($result)) {
            $idarray[] = $id;
        }

        return $idarray;
    }

    //returns an array of ALL child ids for a given id($sel_id)

    /**
     * @param        $sel_id
     * @param string $order
     * @param array  $idarray
     *
     * @return array
     */
    public function getAllChildId($sel_id, string $order = '', array $idarray = []): array
    {
        $sel_id = (int)$sel_id;
        $sql    = 'SELECT ' . $this->id . ' FROM ' . $this->table . ' WHERE ' . $this->pid . '=' . $sel_id;
        if ('' !== $order) {
            $sql .= " ORDER BY $order";
        }
        $result = Utility::queryAndCheck($this->db, $sql);
        $count  = $this->db->getRowsNum($result);
        if (0 == $count) {
            return $idarray;
        }
        while ([$r_id] = $this->db->fetchRow($result)) {
            $idarray[] = $r_id;
            $idarray   = $this->getAllChildId($r_id, $order, $idarray);
        }

        return $idarray;
    }

    //returns an array of ALL parent ids for a given id($sel_id)

    /**
     * @param        $sel_id
     * @param string $order
     * @param array  $idarray
     *
     * @return array
     */
    public function getAllParentId($sel_id, string $order = '', array $idarray = []): array
    {
        $sel_id = (int)$sel_id;
        $sql    = 'SELECT ' . $this->pid . ' FROM ' . $this->table . ' WHERE ' . $this->id . '=' . $sel_id;
        if ('' !== $order) {
            $sql .= " ORDER BY $order";
        }
        $result = Utility::queryAndCheck($this->db, $sql);
        [$r_id] = $this->db->fetchRow($result);
        if (0 == $r_id) {
            return $idarray;
        }
        $idarray[] = $r_id;
        $idarray   = $this->getAllParentId($r_id, $order, $idarray);

        return $idarray;
    }

    //generates path from the root id to a given id($sel_id)
    // the path is delimetered with "/"

    /**
     * @param string|int $sel_id
     * @param string     $title
     * @param string     $path
     *
     * @return string
     */
    public function getPathFromId($sel_id, string $title, string $path = ''): string
    {
        $sel_id = (int)$sel_id;
        $sql    = 'SELECT ' . $this->pid . ', ' . $title . ' FROM ' . $this->table . ' WHERE ' . $this->id . "=$sel_id";
        $result = Utility::queryAndCheck($this->db, $sql);
        if (0 == $this->db->getRowsNum($result)) {
            return $path;
        }
        [$parentid, $name] = $this->db->fetchRow($result);
        $myts = \MyTextSanitizer::getInstance();
        $name = \htmlspecialchars($name, \ENT_QUOTES | \ENT_HTML5);
        $path = '/' . $name . $path;
        if (0 == $parentid) {
            return $path;
        }
        $path = $this->getPathFromId($parentid, $title, $path);

        return $path;
    }

    //makes a nicely ordered selection box
    //$preset_id is used to specify a preselected item
    //set $none to 1 to add an option with value 0

    /**
     * @param        $title
     * @param string $order
     * @param int    $preset_id
     * @param int    $none
     * @param string $sel_name
     * @param string $onchange
     */
    public function makeMySelBox($title, string $order = '', int $preset_id = 0, int $none = 0, string $sel_name = '', string $onchange = ''): void
    {
        if ('' == $sel_name) {
            $sel_name = $this->id;
        }
        $myts = \MyTextSanitizer::getInstance();
        echo "<select name='" . $sel_name . "'";
        if ('' !== $onchange) {
            echo " onchange='" . $onchange . "'";
        }
        echo ">\n";
        $sql = 'SELECT ' . $this->id . ', ' . $title . ' FROM ' . $this->table . ' WHERE ' . $this->pid . '=0';
        if ('' !== $order) {
            $sql .= " ORDER BY $order";
        }
        $result = Utility::queryAndCheck($this->db, $sql);
        if ($none) {
            echo "<option value='0'>----</option>\n";
        }
        while ([$catid, $name] = $this->db->fetchRow($result)) {
            $sel = '';
            if ($catid == $preset_id) {
                $sel = ' selected';
            }
            echo "<option value='$catid'$sel>$name</option>\n";
            $sel = '';
            $arr = $this->getChildTreeArray($catid, $order);
            foreach ($arr as $option) {
                $option['prefix'] = \str_replace('.', '--', $option['prefix']);
                $catpath          = $option['prefix'] . '&nbsp;' . \htmlspecialchars($option[$title], \ENT_QUOTES | \ENT_HTML5);
                if ($option[$this->id] == $preset_id) {
                    $sel = ' selected';
                }
                echo "<option value='" . $option[$this->id] . "'$sel>$catpath</option>\n";
                $sel = '';
            }
        }
        echo "</select>\n";
    }

    //generates nicely formatted linked path from the root id to a given id

    /**
     * @param string|int $sel_id
     * @param string     $title
     * @param string     $funcURL
     * @param string     $path
     *
     * @return string
     */
    public function getNicePathFromId($sel_id, string $title, string $funcURL, string $path = ''): string
    {
        $path   = !empty($path) ? '&nbsp;:&nbsp;' . $path : $path;
        $sel_id = (int)$sel_id;
        $sql    = 'SELECT ' . $this->pid . ', ' . $title . ' FROM ' . $this->table . ' WHERE ' . $this->id . "=$sel_id";
        $result = Utility::queryAndCheck($this->db, $sql);
        if (0 == $this->db->getRowsNum($result)) {
            return $path;
        }
        [$parentid, $name] = $this->db->fetchRow($result);
        $myts = \MyTextSanitizer::getInstance();
        $name = \htmlspecialchars($name, \ENT_QUOTES | \ENT_HTML5);
        $path = "<a href='" . $funcURL . '&amp;' . $this->id . '=' . $sel_id . "'>" . $name . '</a>' . $path;
        if (0 == $parentid) {
            return $path;
        }
        $path = $this->getNicePathFromId($parentid, $title, $funcURL, $path);

        return $path;
    }

    //generates id path from the root id to a given id
    // the path is delimetered with "/"

    /**
     * @param        $sel_id
     * @param string $path
     *
     * @return string
     */
    public function getIdPathFromId($sel_id, string $path = ''): string
    {
        $sel_id = (int)$sel_id;
        $sql    = 'SELECT ' . $this->pid . ' FROM ' . $this->table . ' WHERE ' . $this->id . "=$sel_id";
        $result = Utility::queryAndCheck($this->db, $sql);
        if (0 == $this->db->getRowsNum($result)) {
            return $path;
        }
        [$parentid] = $this->db->fetchRow($result);
        $path = '/' . $sel_id . $path;
        if (0 == $parentid) {
            return $path;
        }
        $path = $this->getIdPathFromId($parentid, $path);

        return $path;
    }

    /**
     * Enter description here...
     *
     * @param int|mixed    $sel_id
     * @param string|mixed $order
     * @param array|mixed  $parray
     *
     * @return mixed
     */
    public function getAllChild($sel_id = 0, $order = '', $parray = [])
    {
        $sel_id = (int)$sel_id;
        $sql    = 'SELECT * FROM ' . $this->table . ' WHERE ' . $this->pid . '=' . $sel_id;
        if ('' !== $order) {
            $sql .= " ORDER BY $order";
        }
        $result = Utility::queryAndCheck($this->db, $sql);
        $count = $this->db->getRowsNum($result);
        if (0 == $count) {
            return $parray;
        }
        while (false !== ($row = $this->db->fetchArray($result))) {
            $parray[] = $row;
            $parray   = $this->getAllChild($row[$this->id], $order, $parray);
        }

        return $parray;
    }

    /**
     * Enter description here...
     *
     * @param int|mixed    $sel_id
     * @param string|mixed $order
     * @param array|mixed  $parray
     * @param string|mixed $r_prefix
     *
     * @return mixed
     */
    public function getChildTreeArray($sel_id = 0, $order = '', $parray = [], $r_prefix = '')
    {
        $sel_id = (int)$sel_id;
        $sql    = 'SELECT * FROM ' . $this->table . ' WHERE ' . $this->pid . '=' . $sel_id;
        if ('' !== $order) {
            $sql .= " ORDER BY $order";
        }
        $result = Utility::queryAndCheck($this->db, $sql);
        $count = $this->db->getRowsNum($result);
        if (0 == $count) {
            return $parray;
        }
        while (false !== ($row = $this->db->fetchArray($result))) {
            $row['prefix'] = $r_prefix . '.';
            $parray[]      = $row;
            $parray        = $this->getChildTreeArray($row[$this->id], $order, $parray, $row['prefix']);
        }

        return $parray;
    }
}
