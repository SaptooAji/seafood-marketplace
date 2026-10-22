package io.github.saptooaji.seafood.seller.core.network

import kotlinx.serialization.SerializationException
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.jsonPrimitive
import retrofit2.Response
import java.io.IOException
import kotlin.coroutines.cancellation.CancellationException

/**
 * Runs one Retrofit call and maps its outcome to [ApiResult]. Laravel answers
 * errors as `{"message": "...", "errors": {"field": ["..."]}}`.
 */
suspend fun <T> apiCall(
    json: Json,
    call: suspend () -> Response<T>,
): ApiResult<T> =
    try {
        call().toApiResult(json)
    } catch (e: CancellationException) {
        throw e
    } catch (_: ServerUnreachableException) {
        ApiResult.ServerUnreachable
    } catch (_: IOException) {
        ApiResult.NetworkError
    } catch (_: SerializationException) {
        // A success answer the app cannot read.
        ApiResult.ServerError
    }

private fun <T> Response<T>.toApiResult(json: Json): ApiResult<T> {
    if (isSuccessful) {
        @Suppress("UNCHECKED_CAST")
        return ApiResult.Success(body() ?: Unit as T)
    }

    val error = ErrorBody.parse(json, errorBody()?.string())
    return when (code()) {
        HttpStatus.UNAUTHORIZED -> ApiResult.Unauthorized
        HttpStatus.FORBIDDEN -> ApiResult.Forbidden(error.message)
        HttpStatus.NOT_FOUND -> ApiResult.NotFound
        HttpStatus.CONFLICT -> ApiResult.Conflict(error.message)
        HttpStatus.UNPROCESSABLE -> ApiResult.Invalid(error.fieldErrors, error.message)
        HttpStatus.TOO_MANY_REQUESTS -> ApiResult.TooManyRequests(headers()["Retry-After"]?.toLongOrNull())
        else -> ApiResult.ServerError
    }
}

private object HttpStatus {
    const val UNAUTHORIZED = 401
    const val FORBIDDEN = 403
    const val NOT_FOUND = 404
    const val CONFLICT = 409
    const val UNPROCESSABLE = 422
    const val TOO_MANY_REQUESTS = 429
}

/** The parts of a Laravel error body the app uses; empty when the body is not JSON. */
private data class ErrorBody(
    val message: String?,
    val fieldErrors: Map<String, List<String>>,
) {
    companion object {
        fun parse(
            json: Json,
            body: String?,
        ): ErrorBody {
            val root =
                body
                    ?.takeIf { it.isNotBlank() }
                    ?.let { runCatching { json.parseToJsonElement(it) }.getOrNull() } as? JsonObject
                    ?: JsonObject(emptyMap())

            val message = (root["message"] as? JsonPrimitive)?.takeIf { it.isString }?.content
            val errors =
                (root["errors"] as? JsonObject).orEmpty().mapValues { (_, messages) ->
                    (messages as? JsonArray).orEmpty().map { it.jsonPrimitive.content }
                }

            return ErrorBody(message, errors)
        }
    }
}
