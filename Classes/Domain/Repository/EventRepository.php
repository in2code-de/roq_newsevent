<?php
namespace ROQUIN\RoqNewsevent\Domain\Repository;

/**
 * Copyright (c) 2012, ROQUIN B.V. (C), http://www.roquin.nl
 *
 * @author:         J. de Groot
 * @file:           EventRepository.php
 * @description:    News event Repository, extending functionality from the News Repository
 */

use GeorgRinger\News\Domain\Model\DemandInterface;
use GeorgRinger\News\Domain\Model\Dto\NewsDemand;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * @package TYPO3
 * @subpackage roq_newsevent
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class EventRepository extends \GeorgRinger\News\Domain\Repository\NewsRepository
{
    /**
     * Returns the constraint to determine if a news event is active or not (archived)
     *
     * @param QueryInterface $query
     * @return \TYPO3\CMS\Extbase\Persistence\Generic\Qom\OrInterface $constraint
     */
    protected function createIsActiveConstraint(QueryInterface $query)
    {
        $timestamp = time(); // + date('Z');

        $constraint = $query->logicalOr(
            [
                // future events:
                $query->greaterThan('tx_roqnewsevent_start', $timestamp),
                // current multiple day events:
                $query->logicalAnd(
                    [
                        // has begun
                        $query->lessThan('tx_roqnewsevent_start', $timestamp),
                        // but is not finished
                        $query->greaterThan('tx_roqnewsevent_end', $timestamp),
                    ]
                ),
            ]
        );

        return $constraint;
    }

    /**
     * @param QueryInterface $query
     * @param NewsDemand $demand (Keep method signature but hint actual type here)
     * @return array
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    protected function createConstraintsFromDemand(QueryInterface $query, DemandInterface $demand)
    {
        $constraints = [];

        if ($demand->getCategories() && $demand->getCategories() !== '0') {
            $constraints[] = $this->createCategoryConstraint(
                $query,
                $demand->getCategories(),
                $demand->getCategoryConjunction(),
                $demand->getIncludeSubCategories()
            );
        }

        if ($demand->getAuthor()) {
            $constraints[] = $query->equals('author', $demand->getAuthor());
        }

        // archived
        if ($demand->getArchiveRestriction() == 'archived') {
            $constraints[] = $query->logicalNot($this->createIsActiveConstraint($query));
            // non-archived (active)
        } elseif ($demand->getArchiveRestriction() == 'active') {
            $constraints[] = $this->createIsActiveConstraint($query);
        }

        // Time restriction greater than or equal
        $timeRestrictionField = $demand->getDateField();
        $timeRestrictionField = (empty($timeRestrictionField)) ? 'datetime' : $timeRestrictionField;

        if ($demand->getTimeRestriction()) {
            $timeLimit = 0;
            // integer = timestamp
            if (\TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($demand->getTimeRestriction())) {
                $timeLimit = $GLOBALS['EXEC_TIME'] - $demand->getTimeRestriction();
            } else {
                // try to check strtotime
                $timeFromString = strtotime($demand->getTimeRestriction());

                if ($timeFromString) {
                    $timeLimit = $timeFromString;
                } else {
                    throw new \Exception(
                        'Time limit Low could not be resolved to an integer. Given was: ' . htmlspecialchars($timeLimit)
                    );
                }
            }

            $constraints[] = $query->greaterThanOrEqual(
                $timeRestrictionField,
                $timeLimit
            );
        }

        // Time restriction less than or equal
        if ($demand->getTimeRestrictionHigh()) {
            $timeLimit = 0;
            // integer = timestamp
            if (\TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($demand->getTimeRestrictionHigh())) {
                $timeLimit = $GLOBALS['EXEC_TIME'] + $demand->getTimeRestrictionHigh();
            } else {
                // try to check strtotime
                $timeFromString = strtotime($demand->getTimeRestrictionHigh());

                if ($timeFromString) {
                    $timeLimit = $timeFromString;
                } else {
                    throw new \Exception(
                        'Time limit High could not be resolved to an integer. Given was: ' . htmlspecialchars(
                            $timeLimit
                        )
                    );
                }
            }

            $constraints[] = $query->lessThanOrEqual(
                $timeRestrictionField,
                $timeLimit
            );
        }

        // top news
        if ($demand->getTopNewsRestriction() == 1) {
            $constraints[] = $query->equals('istopnews', 1);
        } elseif ($demand->getTopNewsRestriction() == 2) {
            $constraints[] = $query->equals('istopnews', 0);
        }

        // storage page
        if ($demand->getStoragePage() != 0) {
            $pidList = GeneralUtility::intExplode(',', $demand->getStoragePage(), true);
            $constraints[] = $query->in('pid', $pidList);
        }

        // month & year OR year only
        if ($demand->getYear() > 0) {
            if (is_null($demand->getDateField())) {
                throw new \InvalidArgumentException('No Datefield is set, therefore no Datemenu is possible!');
            }
            if ($demand->getMonth() > 0) {
                if ($demand->getDay() > 0) {
                    $begin = mktime(0, 0, 0, $demand->getMonth(), $demand->getDay(), $demand->getYear());
                    $end = mktime(23, 59, 59, $demand->getMonth(), $demand->getDay(), $demand->getYear());
                } else {
                    $begin = mktime(0, 0, 0, $demand->getMonth(), 1, $demand->getYear());
                    $end = mktime(23, 59, 59, ($demand->getMonth() + 1), 0, $demand->getYear());
                }
            } else {
                $begin = mktime(0, 0, 0, 1, 1, $demand->getYear());
                $end = mktime(23, 59, 59, 12, 31, $demand->getYear());
            }
            $constraints[] = $query->logicalAnd(
                [
                    $query->greaterThanOrEqual($demand->getDateField(), $begin),
                    $query->lessThanOrEqual($demand->getDateField(), $end),
                ]
            );
        }

        // Tags
        $tags = $demand->getTags();
        if ($tags) {
            $tagList = explode(',', $tags);

            foreach ($tagList as $singleTag) {
                $constraints[] = $query->contains('tags', $singleTag);
            }
        }

        // Search
        $searchConstraints = $this->getSearchConstraints($query, $demand);
        if (!empty($searchConstraints)) {
            $constraints[] = $query->logicalAnd($searchConstraints);
        }

        // Exclude already displayed
        if ($demand->getExcludeAlreadyDisplayedNews()
            && isset($GLOBALS['EXT']['news']['alreadyDisplayed'])
            && !empty($GLOBALS['EXT']['news']['alreadyDisplayed'])) {
            $constraints[] = $query->logicalNot(
                $query->in(
                    'uid',
                    $GLOBALS['EXT']['news']['alreadyDisplayed']
                )
            );
        }

        // events only
        $constraints[] = $query->logicalAnd($query->equals('tx_roqnewsevent_is_event', 1));

        // the event must have an event start date
        $constraints[] = $query->logicalAnd(
            $query->logicalNot(
                $query->equals('tx_roqnewsevent_start', 0)
            )
        );

        // Clean not used constraints
        foreach ($constraints as $key => $value) {
            if (is_null($value)) {
                unset($constraints[$key]);
            }
        }

        return $constraints;
    }
}
