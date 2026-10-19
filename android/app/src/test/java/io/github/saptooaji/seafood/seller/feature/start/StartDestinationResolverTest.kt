package io.github.saptooaji.seafood.seller.feature.start

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.feature.store.FakeStoreRepository
import io.github.saptooaji.seafood.seller.feature.store.FakeStoreRepository.Companion.found
import io.github.saptooaji.seafood.seller.feature.store.domain.CurrentStore
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreStatus
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Test
import org.junit.jupiter.params.ParameterizedTest
import org.junit.jupiter.params.provider.Arguments
import org.junit.jupiter.params.provider.Arguments.arguments
import org.junit.jupiter.params.provider.MethodSource

/** Where a seller lands: one case per state of the token and the store. */
class StartDestinationResolverTest {
    @Test
    fun `without a token the seller signs in`() {
        assertEquals(StartDestination.Login, StartDestinationResolver.resolve(hasToken = false, store = null))
    }

    @ParameterizedTest(name = "{0}")
    @MethodSource("stores")
    fun `with a token the store decides`(
        case: String,
        store: ApiResult<CurrentStore>,
        expected: StartDestination,
    ) {
        assertEquals(expected, StartDestinationResolver.resolve(hasToken = true, store = store), case)
    }

    companion object {
        private fun withStatus(status: StoreStatus) = found(FakeStoreRepository.PENDING_STORE.copy(status = status))

        @JvmStatic
        fun stores(): List<Arguments> =
            listOf(
                arguments("no store yet", FakeStoreRepository.NO_STORE, StartDestination.OpenStore),
                arguments("pending", withStatus(StoreStatus.Pending), StartDestination.StoreStatus),
                arguments("rejected", withStatus(StoreStatus.Rejected), StartDestination.StoreStatus),
                arguments("suspended", withStatus(StoreStatus.Suspended), StartDestination.StoreStatus),
                arguments("a status this app does not know", withStatus(StoreStatus.Unknown), StartDestination.StoreStatus),
                arguments("active", withStatus(StoreStatus.Active), StartDestination.Main),
                arguments("the token was refused", ApiResult.Unauthorized, StartDestination.Login),
                arguments("no connection", ApiResult.NetworkError, StartDestination.Offline),
                arguments("a server error", ApiResult.ServerError, StartDestination.Offline),
            )
    }
}
