package io.github.saptooaji.seafood.seller.core.network

/**
 * The outcome of one API call. Repositories return this instead of throwing,
 * so a screen never sees a raw HTTP or network exception
 * (docs/engineering-standards.md).
 */
sealed interface ApiResult<out T> {
    data class Success<T>(
        val data: T,
    ) : ApiResult<T>

    /** 422: field errors keyed as the server sends them, such as `name` or `variants.0.price`. */
    data class Invalid(
        val fieldErrors: Map<String, List<String>>,
        val message: String?,
    ) : ApiResult<Nothing>

    /**
     * 401. For a signed-in call the session has already ended (see
     * [AuthInterceptor]); on a sign-in call it is an ordinary refusal.
     */
    data object Unauthorized : ApiResult<Nothing>

    /** 403, including a suspended store; [message] is the server's, shown as is. */
    data class Forbidden(
        val message: String?,
    ) : ApiResult<Nothing>

    data object NotFound : ApiResult<Nothing>

    /** 409, such as opening a second store. */
    data class Conflict(
        val message: String?,
    ) : ApiResult<Nothing>

    /**
     * 429; [retryAfterSeconds] comes from the `Retry-After` header when it has
     * one. [message] is the server's: Indonesian from the app's own limits,
     * English ("Too Many Attempts.") from the general API limit.
     */
    data class TooManyRequests(
        val retryAfterSeconds: Long?,
        val message: String? = null,
    ) : ApiResult<Nothing>

    /**
     * 503: a feature that is off or whose provider is failing, such as the AI
     * description (#240). [message] is the server's, shown as is when there is one.
     */
    data class ServiceUnavailable(
        val message: String?,
    ) : ApiResult<Nothing>

    /** Any other 5xx, or an answer the app cannot read. */
    data object ServerError : ApiResult<Nothing>

    /**
     * The server did not answer (refused, timed out, host not found) while
     * the phone itself is online: the trouble is ours, not the seller's (#277).
     */
    data object ServerUnreachable : ApiResult<Nothing>

    /** No answer, and the phone has no working internet connection. */
    data object NetworkError : ApiResult<Nothing>
}
