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
                WHEN NOW() < TIMESTAMP(DATE(`event_date`), TIME(`event_time`)) THEN 'upcoming'
                WHEN NOW() <= TIMESTAMP(DATE(`event_date`), TIME(`end_time`)) THEN 'ongoing'
                ELSE 'completed'
            END
        WHERE LOWER(`event_status`) != 'cancelled'
    ";

    mysqli_query($conn, $sql);
}
