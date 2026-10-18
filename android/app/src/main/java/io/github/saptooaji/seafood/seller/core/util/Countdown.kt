package io.github.saptooaji.seafood.seller.core.util

import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

private const val ONE_SECOND_MILLIS = 1_000L

/**
 * Counts [seconds] down to 1, calling [onTick] once a second with the seconds
 * left, then calls [onDone]. Used to wait out a 429's `Retry-After`.
 */
fun CoroutineScope.launchCountdown(
    seconds: Long,
    onTick: (Long) -> Unit,
    onDone: () -> Unit,
): Job =
    launch {
        for (left in seconds downTo 1) {
            onTick(left)
            delay(ONE_SECOND_MILLIS)
        }
        onDone()
    }
