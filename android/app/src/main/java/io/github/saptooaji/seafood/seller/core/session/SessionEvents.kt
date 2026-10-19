package io.github.saptooaji.seafood.seller.core.session

import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.asSharedFlow
import javax.inject.Inject
import javax.inject.Singleton

sealed interface SessionEvent {
    /** The session ended: the token expired or was revoked (a 401), or the seller signed out. */
    data object SignedOut : SessionEvent

    /**
     * A seller endpoint refused the request because the store is suspended
     * (AC-7). The seller stays signed in; [message] is the server's.
     */
    data class StoreSuspended(
        val message: String?,
    ) : SessionEvent
}

/**
 * Session changes the whole app reacts to. The navigation listens: it sends
 * the seller back to sign in when the session ends, and to the store status
 * when the store is suspended.
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

        fun storeSuspended(message: String?) {
            flow.tryEmit(SessionEvent.StoreSuspended(message))
        }
    }
