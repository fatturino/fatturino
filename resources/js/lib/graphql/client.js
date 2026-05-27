import { cacheExchange, createClient, fetchExchange } from '@urql/core'

export class GraphQLRequestError extends Error {
    constructor(message, { validation = null, graphQLErrors = [], status = 0 } = {}) {
        super(message)
        this.name = 'GraphQLRequestError'
        this.validation = validation
        this.graphQLErrors = graphQLErrors
        this.status = status
    }
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

function normalizeValidation(validationMap) {
    const normalized = {}
    for (const [field, value] of Object.entries(validationMap || {})) {
        normalized[field] = Array.isArray(value) ? value[0] : value
    }
    return normalized
}

const graphqlClient = createClient({
    url: '/graphql',
    exchanges: [cacheExchange, fetchExchange],
    fetchOptions: () => ({
        credentials: 'same-origin',
        headers: {
            'X-CSRF-TOKEN': getCsrfToken(),
        },
    }),
})

export async function graphqlRequest(query, variables = {}) {
    const trimmedQuery = query.trimStart()
    const isMutationOperation = trimmedQuery.startsWith('mutation')
    const operation = isMutationOperation
        ? graphqlClient.mutation(query, variables)
        : graphqlClient.query(query, variables)

    const result = await operation.toPromise()

    if (result.error?.networkError) {
        const status = result.error.networkError?.response?.status ?? 0
        throw new GraphQLRequestError(result.error.message ?? 'Errore HTTP su endpoint GraphQL.', {
            status,
            graphQLErrors: result.error.graphQLErrors ?? [],
        })
    }

    if (result.error?.graphQLErrors?.length) {
        const validation = result.error.graphQLErrors[0]?.extensions?.validation
        throw new GraphQLRequestError(result.error.message ?? 'Errore GraphQL.', {
            status: result.error.networkError?.response?.status ?? 200,
            validation: validation ? normalizeValidation(validation) : null,
            graphQLErrors: result.error.graphQLErrors,
        })
    }

    return result.data ?? {}
}

export function extractValidationErrors(error) {
    if (error instanceof GraphQLRequestError && error.validation) {
        return error.validation
    }

    return null
}

export function extractErrorMessage(error, fallback = 'Errore durante il salvataggio.') {
    if (error instanceof GraphQLRequestError) {
        const firstGraphQLErrorMessage = error.graphQLErrors?.[0]?.message
        if (firstGraphQLErrorMessage) {
            return firstGraphQLErrorMessage
        }

        if (error.message) {
            return error.message
        }
    }

    if (error instanceof Error && error.message) {
        return error.message
    }

    return fallback
}
