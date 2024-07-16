/*
 * Copyright Elasticsearch B.V. and/or licensed to Elasticsearch B.V. under one
 * or more contributor license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

#pragma once



#define ELASTIC_OTEL_PP_CONCAT_IMPL( token1, token2 ) token1##token2
#define ELASTIC_OTEL_PP_CONCAT( token1, token2 ) ELASTIC_OTEL_PP_CONCAT_IMPL( token1, token2 )

#define ELASTIC_OTEL_ZEND_ADD_ASSOC( map, key, valueType, value ) ELASTIC_OTEL_PP_CONCAT( ELASTIC_OTEL_PP_CONCAT( add_assoc_, valueType ), _ex)( (map), (key), sizeof( key ) - 1, (value) )

#define ELASTIC_OTEL_ZEND_ADD_ASSOC_NULLABLE_STRING( map, key, value ) \
    do { \
        if ( (value) == NULL ) \
        { \
            zval elastic_otel_zend_add_assoc_nullable_string_aux_zval; \
            ZVAL_NULL( &elastic_otel_zend_add_assoc_nullable_string_aux_zval ); \
            add_assoc_zval_ex( (map), (key), sizeof( key ) - 1, &elastic_otel_zend_add_assoc_nullable_string_aux_zval ); \
        } \
        else \
        { \
            add_assoc_string_ex( (map), (key), sizeof( key ) - 1, (value) ); \
        } \
    } while( 0 ) \
    /**/
