<?php

/**
 * Recompute event_status from schedule. Uses event_date for the calendar day
 * and TIME(event_time) / TIME(end_time) for clock times (works with DATE+TIME
 * or legacy DATETIME columns).
 */
function updateEventStatuses(mysqli $conn): void
{
    $sql = "
        UPDATE `event`
        SET `event_status` =
            CASE
                WHEN LOWER(`event_status`) = 'cancelled' THEN 'cancelled'
                WHEN NOW() > TIMESTAMP(DATE(`event_date`), TIME(`end_time`)) THEN 'completed'
                WHEN NOW() BETWEEN TIMESTAMP(DATE(`event_date`), TIME(`event_time`))
                     AND TIMESTAMP(DATE(`event_date`), TIME(`end_time`)) THEN 'ongoing'
                WHEN (
                    SELECT COUNT(*)
                    FROM `eventregistration` er
                    WHERE er.`event_id` = `event`.`event_id`
                      AND er.`registration_status` = 'registered'
                ) >= `max_participant` THEN 'full'
                WHEN `registration_open` = 1 THEN 'open'
                WHEN NOW() < TIMESTAMP(DATE(`event_date`), TIME(`event_time`)) THEN 'upcoming'
                ELSE 'completed'
            END
        WHERE LOWER(`event_status`) != 'cancelled'
    ";

    mysqli_query($conn, $sql);
}
