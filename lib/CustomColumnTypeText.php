<?php
/**
 * COPS (Calibre OPDS PHP Server) class file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 */

require_once dirname(__FILE__) . '/SQLQueries.php';

class CustomColumnTypeText extends CustomColumnType
{
    private static $type;

    protected function __construct($pcustomId, $datatype)
    {

        self::$type = $datatype;

        switch ($datatype) {
            case self::CUSTOM_TYPE_TEXT:
                parent::__construct($pcustomId, self::CUSTOM_TYPE_TEXT);
                break;
            case self::CUSTOM_TYPE_ENUM:
                parent::__construct($pcustomId, self::CUSTOM_TYPE_ENUM);
                break;
            case self::CUSTOM_TYPE_SERIES:
                parent::__construct($pcustomId, self::CUSTOM_TYPE_SERIES);
                break;
            default:
                throw new UnexpectedValueException;
        }
        parent::__construct($pcustomId, self::CUSTOM_TYPE_TEXT);
    }

    /**
     * Get the name of the sqlite table for this column
     *
     * @return string|null
     */
    private function getTableName()
    {
        return "custom_column_{$this->customId}";
    }

    /**
     * Get the name of the linking sqlite table for this column
     * (or NULL if there is no linktable)
     *
     * @return string|null
     */
    private function getTableLinkName()
    {
        return "books_custom_column_{$this->customId}_link";
    }

    /**
     * Get the name of the linking column in the linktable
     *
     * @return string|null
     */
    private function getTableLinkColumn()
    {
        return "value";
    }

    public function getQuery($id)
    {
        $query = str_format(SQL_BOOKS_BY_CUSTOM, "{0}", "{1}", $this->getTableLinkName(), $this->getTableLinkColumn());
        return array($query, array($id));
    }

    public function getCustom($id)
    {
        $result = $this->getDb()->prepare(str_format("SELECT id, value AS name FROM {0} WHERE id = ?", $this->getTableName()));
        $result->execute(array($id));
        if ($post = $result->fetchObject()) {
            return new CustomColumn($id, $post->name, $this);
        }
        return NULL;
    }

    protected function getAllCustomValuesFromDatabase()
    {
        $queryFormat = "SELECT {0}.id AS id, {0}.value AS name, count(*) AS count FROM {0}, {1} WHERE {0}.id = {1}.{2} GROUP BY {0}.id, {0}.value ORDER BY {0}.value";
        $query = str_format($queryFormat, $this->getTableName(), $this->getTableLinkName(), $this->getTableLinkColumn());

        $result = $this->getDb()->query($query);
        $entryArray = array();
        while ($post = $result->fetchObject())
        {
            $entryPContent = str_format(localize("bookword", $post->count), $post->count);
            $entryPLinkArray = array(new LinkNavigation ($this->getUri($post->id)));

            $entry = new Entry($post->name, $this->getEntryId($post->id), $entryPContent, $this->datatype, $entryPLinkArray, "", $post->count);

            array_push($entryArray, $entry);
        }
        return $entryArray;
    }

    public function getDescription()
    {
        switch (self::$type) {
            case self::CUSTOM_TYPE_TEXT:
                $desc = $this->getDatabaseDescription();
                if ($desc === NULL || empty($desc)) {
                    $desc = str_format(localize("customcolumn.description"), $this->getTitle());
                }
                return $desc;
            case self::CUSTOM_TYPE_ENUM:
            case self::CUSTOM_TYPE_SERIES:
                return str_format(localize("customcolumn.description.".self::$type, $this->getDistinctValueCount()), $this->getDistinctValueCount());
            default:
                throw new UnexpectedValueException;
        }
    }

    public function getCustomByBook($book)
    {
        switch (self::$type) {
            case self::CUSTOM_TYPE_TEXT:
                $queryFormat = "SELECT {0}.id AS id, {0}.{2} AS name FROM {0}, {1} WHERE {0}.id = {1}.{2} AND {1}.book = {3} ORDER BY {0}.value";
                break;
            case self::CUSTOM_TYPE_ENUM:
                $queryFormat = "SELECT {0}.id AS id, {0}.{2} AS name FROM {0}, {1} WHERE {0}.id = {1}.{2} AND {1}.book = {3}";
                break;
            case self::CUSTOM_TYPE_SERIES:
                $queryFormat = "SELECT {0}.id AS id, {1}.{2} AS name, {1}.extra AS extra FROM {0}, {1} WHERE {0}.id = {1}.{2} AND {1}.book = {3}";
                break;
            default:
                throw new UnexpectedValueException;
        }
        $query = str_format($queryFormat, $this->getTableName(), $this->getTableLinkName(), $this->getTableLinkColumn(), $book->id);

        $result = $this->getDb()->query($query);
        if ($post = $result->fetchObject()) {
            switch (self::$type) {
                case self::CUSTOM_TYPE_TEXT:
                case self::CUSTOM_TYPE_ENUM:
                    return new CustomColumn($post->id, $post->name, $this);
                    break;
                case self::CUSTOM_TYPE_SERIES:
                    return new CustomColumn($post->id, $post->name . " [" . $post->extra . "]", $this);
                    break;
                default:
                    throw new UnexpectedValueException;
            }
        }
        return new CustomColumn(NULL, "", $this);
    }

    public function isSearchable()
    {
        return true;
    }
}
