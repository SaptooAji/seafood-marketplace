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

    /** 429; [retryAfterSeconds] comes from the `Retry-After` header when it has one. */
    data class TooManyRequests(
        val retryAfterSeconds: Long?,
    ) : ApiResult<Nothing>

    /** 5xx, or an answer the app cannot read. */
    data object ServerError : ApiResult<Nothing>

    /** No connection, or the server did not answer in time. */
    data object NetworkError : ApiResult<Nothing>
}
