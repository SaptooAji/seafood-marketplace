package io.github.saptooaji.seafood.seller.core.session

import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.asSharedFlow
import javax.inject.Inject
import javax.inject.Singleton

sealed interface SessionEvent {
    /** The session ended: the token expired or was revoked (a 401), or the seller signed out. */
    data object SignedOut : SessionEvent
}

/**
 * Session changes the whole app reacts to. MainActivity listens and sends the
 * seller back to sign in when the session ends.
 */
@Singleton
class SessionEvents
    @Inject
    constructor() {
        private val flow = MutableSharedFlow<SessionEvent>(extraBufferCapacity = 8)

        val events: SharedFlow<SessionEvent> = flow.asSharedFlow()

        fun signedOut() {
            flow.tryEmit(SessionEvent.SignedOut)
        }
    }
