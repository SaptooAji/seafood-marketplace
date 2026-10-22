package io.github.saptooaji.seafood.seller.core.network

/** Maps the data of a [ApiResult.Success]; every failure passes through unchanged. */
inline fun <T, R> ApiResult<T>.map(transform: (T) -> R): ApiResult<R> =
    when (this) {
        is ApiResult.Success -> ApiResult.Success(transform(data))
        is ApiResult.Invalid -> this
        ApiResult.Unauthorized -> ApiResult.Unauthorized
        is ApiResult.Forbidden -> this
        ApiResult.NotFound -> ApiResult.NotFound
        is ApiResult.Conflict -> this
        is ApiResult.TooManyRequests -> this
        ApiResult.ServerError -> ApiResult.ServerError
        ApiResult.ServerUnreachable -> ApiResult.ServerUnreachable
        ApiResult.NetworkError -> ApiResult.NetworkError
    }
