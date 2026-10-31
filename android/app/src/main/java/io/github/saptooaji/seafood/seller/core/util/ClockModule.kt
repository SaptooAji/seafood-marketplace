package io.github.saptooaji.seafood.seller.core.util

import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.components.SingletonComponent
import java.time.Clock

/**
 * The clock is injected, never read as `Instant.now()` in a class, so tests
 * can fix the day and the time zone. It follows the phone's time zone.
 */
@Module
@InstallIn(SingletonComponent::class)
object ClockModule {
    @Provides
    fun clock(): Clock = Clock.systemDefaultZone()
}
