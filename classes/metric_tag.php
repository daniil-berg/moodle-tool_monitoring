<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Definition of the {@see metric_tag} class.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_monitoring;

use context_system;
use core\exception\coding_exception;
use core\exception\moodle_exception;
use core_cache\cache;
use core_cache\cacheable_object_interface;
use core_tag_area;
use core_tag_tag;
use dml_exception;
use moodle_url;
use stdClass;
use tool_monitoring\exceptions\tag_not_found;
use Traversable;

/**
 * Convenience class that maps instances to records in the `tag` table, but only those related to the monitoring tag collection.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class metric_tag extends core_tag_tag implements cacheable_object_interface {
    /** @var string Name of the associated tag area. */
    public const ITEM_TYPE = registered_metric::TABLE;

    /** @var string Name of the associated tag collection. */
    public const COLLECTION_NAME = 'monitoring';

    /** @var string List of fields of interest for fetching from the DB. */
    private const DB_TABLE_FIELDS = 'name,id,userid,tagcollid,rawname,isstandard,description,descriptionformat,flag,timemodified';

    /** @var array<string, string> Properties of interest that are cached; for convenience, keys and values are the same. */
    private const CACHE_FIELDS = [
        'id' => 'id',
        'userid' => 'userid',
        'name' => 'name',
        'rawname' => 'rawname',
        'isstandard' => 'isstandard',
        'description' => 'description',
        'descriptionformat' => 'descriptionformat',
        'flag' => 'flag',
        'timemodified' => 'timemodified',
        'taginstanceid' => 'taginstanceid',
        'taginstancecontextid' => 'taginstancecontextid',
    ];

    /**
     * Returns the ID of the tag collection associated with our {@see self::ITEM_TYPE `ITEM_TYPE`}.
     *
     * @return int Tag collection ID.
     * @throws coding_exception Tag area for our {@see self::ITEM_TYPE `ITEM_TYPE`} not found.
     */
    public static function get_collection_id(): int {
        // This function caches the result, so we don't need to worry about it.
        $tagarea = core_tag_area::get_areas()[self::ITEM_TYPE]['tool_monitoring'] ?? null;
        if (is_null($tagarea)) {
            throw new coding_exception("Could not find the '" . self::ITEM_TYPE . "' tag area"); // @codeCoverageIgnore
        }
        return $tagarea->tagcollid;
    }

    /**
     * Fetches all tags from our collection from the database.
     *
     * @return Traversable<string, static> Instances indexed by tag name.
     * @throws coding_exception Tag area for our {@see self::ITEM_TYPE `ITEM_TYPE`} not found.
     * @throws dml_exception
     */
    private static function fetch_all(): Traversable {
        global $DB;
        $recordset = $DB->get_recordset(
            table:      'tag',
            conditions: ['tagcollid' => static::get_collection_id()],
            fields:     self::DB_TABLE_FIELDS,
        );
        foreach ($recordset as $name => $record) {
            $tag = new static($record);
            // No idea why this exists as a separate property; it is uncorrelated with the record's `timemodified` field.
            unset($tag->timemodified);
            yield $name => $tag;
        }
    }

    /**
     * Returns all tags with the given names.
     *
     * Attempts to get them from the cache first and only queries the DB if names were not found in the cache.
     *
     * **NOTE**: This function does explicit `null`-caching for those names that are not in the DB. This means if a name was not
     * found in the DB, that fact will be cached. Subsequent calls with the same name will no longer check the DB until that cache
     * entry is removed.
     *
     * @param string ...$names Names of tags to fetch. Will be normalized before looking up the tags.
     * @return array<string, static> Instances with matching names, indexed by their normalized names.
     * @throws coding_exception Tag area for our {@see self::ITEM_TYPE `ITEM_TYPE`} not found.
     * @throws dml_exception
     * @throws tag_not_found At least one of the provided `$names` does not match any existing metric tag.
     */
    public static function get_all_with_names(string ...$names): array {
        if (empty($names)) {
            return [];
        }
        $names = parent::normalize($names);
        $cache = cache::make('tool_monitoring', 'metric_tags');
        $tags = array_filter($cache->get_many($names));
        $missingtags = array_diff_key(array_fill_keys($names, null), $tags);
        if ($missingtags) {
            // Cache miss for at least one name. Fetch all tags and cache them.
            // Do explicit `null`-caching for those names that are not in the DB to avoid querying it again.
            $alltags = iterator_to_array(static::fetch_all());
            $cache->set_many(array_merge($missingtags, $alltags));
            // Go through all previously missing tags and add those from the DB to the `$tags` array.
            // If one of them is still missing, throw an exception.
            foreach (array_keys($missingtags) as $name) {
                if (!array_key_exists($name, $alltags)) {
                    throw new tag_not_found($name, self::COLLECTION_NAME);
                }
                $tags[$name] = $alltags[$name];
            }
        }
        return $tags;
    }

    /**
     * Returns the tags associated with the metrics with the given IDs.
     *
     * @param int ...$ids Registered metric IDs to get the tags for.
     * @return array<array<string, static>> Array where the keys are the metric IDs and the values are associative arrays of tags
     *                                      mapped to their normalized names. If tags are disabled, the inner arrays will be empty.
     */
    public static function get_for_metric_ids(int ...$ids): array {
        $arrays = parent::get_items_tags('tool_monitoring', self::ITEM_TYPE, $ids);
        // Reindex the nested arrays by name.
        array_walk($arrays, fn (array &$array, int|string $id) => $array = array_column($array, null, 'name'));
        // If tags are disabled, the `parent::get_items_tags` method just returns an empty array.
        // We want to ensure the output is always an array indexed by the provided IDs, even if the inner arrays are empty.
        return $arrays + array_fill_keys($ids, []);
    }

    /**
     * Sets tags for the given metric.
     *
     * Only actually performs DB queries if tags were either added, removed, or their order changed.
     *
     * @param int|registered_metric $metric Either the ID of the metric or the metric instance.
     * @param string ...$tagnames Names of tags to set.
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function set_for_metric(int|registered_metric $metric, string ...$tagnames): void {
        if ($metric instanceof registered_metric) {
            $metric = $metric->id;
        }
        parent::set_item_tags(
            component: 'tool_monitoring',
            itemtype: self::ITEM_TYPE,
            itemid: $metric,
            context: context_system::instance(),
            tagnames: $tagnames,
        );
    }

    /**
     * Removes all tags from the given metric.
     *
     * @param int|registered_metric $metric Either the ID of the metric or the metric instance.
     * @throws dml_exception
     */
    public static function remove_all_for_metric(int|registered_metric $metric): void {
        if ($metric instanceof registered_metric) {
            $metric = $metric->id;
        }
        parent::remove_all_item_tags(
            component: 'tool_monitoring',
            itemtype: self::ITEM_TYPE,
            itemid: $metric,
        );
    }

    /**
     * Returns the URL to edit the tag.
     *
     * @return moodle_url URL to the tag editing page.
     * @throws moodle_exception
     */
    public function get_edit_url(): moodle_url {
        return new moodle_url('/tag/edit.php', ['id' => $this->id]);
    }

    /**
     * Returns the URL to the tag management page.
     *
     * @return moodle_url URL to the tag management page.
     * @throws moodle_exception
     */
    public static function get_manage_url(): moodle_url {
        return new moodle_url('/tag/manage.php', ['tc' => self::get_collection_id()]);
    }

    /**
     * Convenience override for the {@see core_tag_tag::is_enabled} method.
     *
     * @param string|null $component Name of the component responsible for tagging; `null` (default) means this plugin.
     * @param string|null $itemtype Name of the item type; `null` (default) means {@see self::ITEM_TYPE `ITEM_TYPE`}.
     * @return bool Whether tags in general **and** our tag area in particular are enabled.
     */
    #[\Override]
    public static function is_enabled($component = null, $itemtype = null): bool {
        return parent::is_enabled($component ?? 'tool_monitoring', $itemtype ?? self::ITEM_TYPE);
    }

    #[\Override]
    public function prepare_to_cache(): array {
        return array_map(fn (string $field) => $this->$field ?? null, self::CACHE_FIELDS);
    }

    /**
     * Constructs a new instance from data stored in the cache.
     *
     * @param array<string, mixed>|stdClass $data Data to use for construction.
     * @return self New instance.
     * @throws coding_exception Data has an unexpected type or is missing required fields.
     */
    #[\Override]
    public static function wake_from_cache(mixed $data): self {
        if ($data instanceof stdClass) {
            $data = (array) $data;
        } else if (!is_array($data) || array_is_list($data)) {
            throw new coding_exception('Received unexpected data type for metric_tag from cache: ' . gettype($data));
        }
        $missing = array_diff_key(self::CACHE_FIELDS, $data);
        if (!empty($missing)) {
            throw new coding_exception('Missing cache fields for metric_tag: ' . implode(', ', $missing));
        }
        $extra = array_diff_key($data, self::CACHE_FIELDS);
        if (!empty($extra)) {
            debugging("Unexpected cache fields for metric_tag {$data['id']}:" . implode(', ', $extra), DEBUG_DEVELOPER);
        }
        $data['tagcollid'] = static::get_collection_id();
        $tag = new static((object) $data);
        // No idea why this exists as a separate property; it is uncorrelated with the record's `timemodified` field.
        unset($tag->timemodified);
        return $tag;
    }
}
