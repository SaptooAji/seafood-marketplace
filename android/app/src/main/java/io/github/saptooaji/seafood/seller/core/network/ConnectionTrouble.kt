package io.github.saptooaji.seafood.seller.core.network

/** Why a screen could not load (#277): the phone has no internet, or our server is down. */
enum class ConnectionTrouble {
    NoInternet,
    Server,
    ;

    companion object {
        /** For a failed result: only [ApiResult.NetworkError] is the phone's trouble. */
        fun of(result: ApiResult<*>): ConnectionTrouble = if (result == ApiResult.NetworkError) NoInternet else Server
    }
}
