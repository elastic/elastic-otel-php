#include "OtlpExporter.h"
#include "OtlpExporter/LogsConverter.h"
#include "OtlpExporter/MetricConverter.h"
#include "OtlpExporter/SpanConverter.h"

#include "PhpBridge.h"
#include "LogFeature.h"
#include "ModuleGlobals.h"

#include <php.h>
#include <Zend/zend_interfaces.h>
#include <Zend/zend_exceptions.h>
#include <Zend/zend_types.h>

zend_class_entry *span_exporter_ce = nullptr;
zend_class_entry *span_exporter_iface_ce = nullptr;
zend_class_entry *cancellation_iface_ce = nullptr;

using namespace std::string_view_literals;

PHP_METHOD(SpanExporter, __construct) {
    zval *transport;

    if (zend_parse_parameters(ZEND_NUM_ARGS(), "z", &transport) == FAILURE) {
        RETURN_THROWS();
    }
    zend_update_property(span_exporter_ce, Z_OBJ_P(ZEND_THIS), "transport", sizeof("transport") - 1, transport);
}

PHP_METHOD(SpanExporter, shutdown) {
    zval *cancellation = NULL;
    zval *transport;
    zval retval;

    if (zend_parse_parameters(ZEND_NUM_ARGS(), "|O!", &cancellation, cancellation_iface_ce) == FAILURE) {
        RETURN_THROWS();
    }

    transport = zend_read_property(span_exporter_ce, Z_OBJ_P(ZEND_THIS), "transport", sizeof("transport") - 1, 0, NULL);

    if (!transport || Z_TYPE_P(transport) != IS_OBJECT) {
        ELOGF_WARNING(EAPM_GL(logger_).get(), OTLPEXPORT, "Invalid transport");
        zend_throw_exception(NULL, "Invalid transport", 0);
        RETURN_THROWS();
    }

    zend_call_method(Z_OBJ_P(transport), Z_OBJCE_P(transport), NULL, "shutdown", strlen("shutdown"), &retval, 1, cancellation ? cancellation : &EG(uninitialized_zval), NULL);
    RETURN_ZVAL(&retval, 1, 1);
}

PHP_METHOD(SpanExporter, forceFlush) {
    zval *cancellation = NULL;
    zval *transport;
    zval retval;

    if (zend_parse_parameters(ZEND_NUM_ARGS(), "|O!", &cancellation, cancellation_iface_ce) == FAILURE) {
        RETURN_THROWS();
    }

    transport = zend_read_property(span_exporter_ce, Z_OBJ_P(ZEND_THIS), "transport", sizeof("transport") - 1, 0, NULL);

    if (!transport || Z_TYPE_P(transport) != IS_OBJECT) {
        ELOGF_WARNING(EAPM_GL(logger_).get(), OTLPEXPORT, "Invalid transport");
        zend_throw_exception(NULL, "Invalid transport", 0);
        RETURN_THROWS();
    }

    zend_call_method(Z_OBJ_P(transport), Z_OBJCE_P(transport), NULL, "forceFlush", strlen("forceFlush"), &retval, 1, cancellation ? cancellation : &EG(uninitialized_zval), NULL);
    RETURN_ZVAL(&retval, 1, 1);
}

PHP_METHOD(SpanExporter, export) {
    zval *batch;
    zval *cancellation = nullptr;

    ZEND_PARSE_PARAMETERS_START(1, 2)
    Z_PARAM_ZVAL(batch)
    Z_PARAM_OPTIONAL
    Z_PARAM_ZVAL(cancellation)
    ZEND_PARSE_PARAMETERS_END();

    zval *transport = zend_read_property(span_exporter_ce, Z_OBJ_P(ZEND_THIS), "transport", sizeof("transport") - 1, 0, NULL);
    if (!transport || Z_TYPE_P(transport) != IS_OBJECT) {
        ELOGF_WARNING(EAPM_GL(logger_).get(), OTLPEXPORT, "Invalid transport");
        zend_throw_exception(NULL, "Invalid transport", 0);
        RETURN_THROWS();
    }

    try {
        elasticapm::php::SpanConverter converter;
        elasticapm::php::AutoZval b(batch);

        std::array<elasticapm::php::AutoZval, 2> args{converter.getStringSerialized(b), elasticapm::php::AutoZval((cancellation && Z_TYPE_P(cancellation) == IS_OBJECT) ? cancellation : &EG(uninitialized_zval))};

        elasticapm::php::AutoZval retVal;
        if (!elasticapm::php::callMethod(transport, "send"sv, args.data()->get(), args.size(), retVal.get())) {
            zend_throw_exception(NULL, "Failed to call send() on transport", 0);
            ELOGF_WARNING(EAPM_GL(logger_).get(), OTLPEXPORT, "Failed to call send() on transport");
            RETURN_THROWS();
        }
        RETURN_ZVAL(retVal.get(), 1, 0);

    } catch (std::runtime_error const &e) {
        ELOGF_WARNING(EAPM_GL(logger_).get(), OTLPEXPORT, "Failed to serialize span batch: '%s'", e.what());
        zend_throw_exception_ex(NULL, 0, "Failed to serialize span batch: '%s'", e.what());
        RETURN_THROWS();
    }
}

ZEND_BEGIN_ARG_INFO_EX(arginfo_spanexporter___construct, 0, 0, 1)
ZEND_ARG_OBJ_INFO(0, transport, OpenTelemetry\\Contrib\\Otlp\\TransportInterface, 0)
ZEND_END_ARG_INFO()
ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_spanexporter_export, 0, 1, FutureInterface, 0)
ZEND_ARG_TYPE_INFO(0, batch, IS_ITERABLE, 0)
ZEND_ARG_OBJ_INFO_WITH_DEFAULT_VALUE(0, cancellation, OpenTelemetry\\SDK\\Common\\Future\\CancellationInterface, 1, "null")
ZEND_END_ARG_INFO()
ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_spanexporter_shutdown, 0, 0, _IS_BOOL, 0)
ZEND_ARG_OBJ_INFO_WITH_DEFAULT_VALUE(0, cancellation, OpenTelemetry\\SDK\\Common\\Future\\CancellationInterface, 1, "null")
ZEND_END_ARG_INFO()
ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_spanexporter_forceFlush, 0, 0, _IS_BOOL, 0)
ZEND_ARG_OBJ_INFO_WITH_DEFAULT_VALUE(0, cancellation, OpenTelemetry\\SDK\\Common\\Future\\CancellationInterface, 1, "null")
ZEND_END_ARG_INFO()

// clang-format off
const zend_function_entry OtlpExporterFunctions[] = {
    PHP_ME(SpanExporter, __construct, arginfo_spanexporter___construct, ZEND_ACC_PUBLIC)
    PHP_ME(SpanExporter, export,      arginfo_spanexporter_export,      ZEND_ACC_PUBLIC)
    PHP_ME(SpanExporter, shutdown,    arginfo_spanexporter_shutdown,    ZEND_ACC_PUBLIC)
    PHP_ME(SpanExporter, forceFlush,  arginfo_spanexporter_forceFlush,  ZEND_ACC_PUBLIC)

    PHP_FE_END
};
// clang-format on

void RegisterOtlpExporterClasses() {
    zend_class_entry ce;

    INIT_NS_CLASS_ENTRY(ce, "OpenTelemetry\\SDK\\Trace", "SpanExporterInterface", nullptr);
    span_exporter_iface_ce = zend_register_internal_interface(&ce);

    INIT_NS_CLASS_ENTRY(ce, "OpenTelemetry\\SDK\\Trace", "CancellationInterface", nullptr);
    cancellation_iface_ce = zend_register_internal_interface(&ce);

    INIT_NS_CLASS_ENTRY(ce, "OpenTelemetry\\Contrib\\Otlp", "SpanExporter", OtlpExporterFunctions);
    span_exporter_ce = zend_register_internal_class(&ce);
    zend_class_implements(span_exporter_ce, 1, span_exporter_iface_ce);
    span_exporter_ce->ce_flags |= ZEND_ACC_FINAL;

    zend_declare_property_null(span_exporter_ce, "transport", sizeof("transport") - 1, ZEND_ACC_PRIVATE);
}

zend_class_entry *logs_exporter_ce = nullptr;
zend_class_entry *logs_exporter_iface_ce = nullptr;

PHP_METHOD(LogsExporter, __construct) {
    zval *transport;
    if (zend_parse_parameters(ZEND_NUM_ARGS(), "z", &transport) == FAILURE) {
        RETURN_THROWS();
    }
    zend_update_property(logs_exporter_ce, Z_OBJ_P(ZEND_THIS), "transport", sizeof("transport") - 1, transport);
}

PHP_METHOD(LogsExporter, shutdown) {
    zval *cancellation = NULL;
    zval *transport;
    zval retval;

    if (zend_parse_parameters(ZEND_NUM_ARGS(), "|O!", &cancellation, cancellation_iface_ce) == FAILURE) {
        RETURN_THROWS();
    }

    transport = zend_read_property(logs_exporter_ce, Z_OBJ_P(ZEND_THIS), "transport", sizeof("transport") - 1, 0, NULL);

    if (!transport || Z_TYPE_P(transport) != IS_OBJECT) {
        ELOGF_WARNING(EAPM_GL(logger_).get(), OTLPEXPORT, "Invalid transport");
        zend_throw_exception(NULL, "Invalid transport", 0);
        RETURN_THROWS();
    }

    zend_call_method(Z_OBJ_P(transport), Z_OBJCE_P(transport), NULL, "shutdown", strlen("shutdown"), &retval, 1, cancellation ? cancellation : &EG(uninitialized_zval), NULL);
    RETURN_ZVAL(&retval, 1, 1);
}

PHP_METHOD(LogsExporter, forceFlush) {
    zval *cancellation = NULL;
    zval *transport;
    zval retval;

    if (zend_parse_parameters(ZEND_NUM_ARGS(), "|O!", &cancellation, cancellation_iface_ce) == FAILURE) {
        RETURN_THROWS();
    }

    transport = zend_read_property(logs_exporter_ce, Z_OBJ_P(ZEND_THIS), "transport", sizeof("transport") - 1, 0, NULL);

    if (!transport || Z_TYPE_P(transport) != IS_OBJECT) {
        ELOGF_WARNING(EAPM_GL(logger_).get(), OTLPEXPORT, "Invalid transport");
        zend_throw_exception(NULL, "Invalid transport", 0);
        RETURN_THROWS();
    }

    zend_call_method(Z_OBJ_P(transport), Z_OBJCE_P(transport), NULL, "forceFlush", strlen("forceFlush"), &retval, 1, cancellation ? cancellation : &EG(uninitialized_zval), NULL);
    RETURN_ZVAL(&retval, 1, 1);
}

PHP_METHOD(LogsExporter, export) {
    zval *batch;
    zval *cancellation = nullptr;

    ZEND_PARSE_PARAMETERS_START(1, 2)
    Z_PARAM_ZVAL(batch)
    Z_PARAM_OPTIONAL
    Z_PARAM_ZVAL(cancellation)
    ZEND_PARSE_PARAMETERS_END();

    zval *transport = zend_read_property(logs_exporter_ce, Z_OBJ_P(ZEND_THIS), "transport", sizeof("transport") - 1, 0, NULL);
    if (!transport || Z_TYPE_P(transport) != IS_OBJECT) {
        ELOGF_WARNING(EAPM_GL(logger_).get(), OTLPEXPORT, "Invalid transport");
        zend_throw_exception(NULL, "Invalid transport", 0);
        RETURN_THROWS();
    }

    try {
        elasticapm::php::LogsConverter converter;
        elasticapm::php::AutoZval b(batch);

        std::array<elasticapm::php::AutoZval, 2> args{converter.getStringSerialized(b), elasticapm::php::AutoZval((cancellation && Z_TYPE_P(cancellation) == IS_OBJECT) ? cancellation : &EG(uninitialized_zval))};

        elasticapm::php::AutoZval retVal;
        if (!elasticapm::php::callMethod(transport, "send"sv, args.data()->get(), args.size(), retVal.get())) {
            ELOGF_WARNING(EAPM_GL(logger_).get(), OTLPEXPORT, "Failed to call send() on transport");
            zend_throw_exception(NULL, "Failed to call send() on transport", 0);
            RETURN_THROWS();
        }

        RETURN_ZVAL(retVal.get(), 1, 0);
    } catch (std::exception const &e) {
        ELOGF_WARNING(EAPM_GL(logger_).get(), OTLPEXPORT, "Failed to serialize logs batch: '%s'", e.what());
        zend_throw_exception_ex(NULL, 0, "Failed to serialize logs batch: '%s'", e.what());
        RETURN_THROWS();
    }
}

ZEND_BEGIN_ARG_INFO_EX(arginfo_logsexporter___construct, 0, 0, 1)
ZEND_ARG_OBJ_INFO(0, transport, OpenTelemetry\\Contrib\\Otlp\\TransportInterface, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_logsexporter_export, 0, 1, FutureInterface, 0)
ZEND_ARG_TYPE_INFO(0, batch, IS_ITERABLE, 0)
ZEND_ARG_OBJ_INFO_WITH_DEFAULT_VALUE(0, cancellation, OpenTelemetry\\SDK\\Common\\Future\\CancellationInterface, 1, "null")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_logsexporter_shutdown, 0, 0, _IS_BOOL, 0)
ZEND_ARG_OBJ_INFO_WITH_DEFAULT_VALUE(0, cancellation, OpenTelemetry\\SDK\\Common\\Future\\CancellationInterface, 1, "null")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_logsexporter_forceFlush, 0, 0, _IS_BOOL, 0)
ZEND_ARG_OBJ_INFO_WITH_DEFAULT_VALUE(0, cancellation, OpenTelemetry\\SDK\\Common\\Future\\CancellationInterface, 1, "null")
ZEND_END_ARG_INFO()

// clang-format off
const zend_function_entry LogsExporterFunctions[] = {
    PHP_ME(LogsExporter, __construct, arginfo_logsexporter___construct, ZEND_ACC_PUBLIC)
    PHP_ME(LogsExporter, export, arginfo_logsexporter_export, ZEND_ACC_PUBLIC)
    PHP_ME(LogsExporter, shutdown, arginfo_logsexporter_shutdown, ZEND_ACC_PUBLIC)
    PHP_ME(LogsExporter, forceFlush, arginfo_logsexporter_forceFlush, ZEND_ACC_PUBLIC)
    PHP_FE_END
};
// clang-format on

void RegisterLogsExporterClasses() {
    zend_class_entry ce;

    INIT_NS_CLASS_ENTRY(ce, "OpenTelemetry\\SDK\\Logs", "LogRecordExporterInterface", nullptr);
    logs_exporter_iface_ce = zend_register_internal_interface(&ce);

    INIT_NS_CLASS_ENTRY(ce, "OpenTelemetry\\Contrib\\Otlp", "LogsExporter", LogsExporterFunctions);
    logs_exporter_ce = zend_register_internal_class(&ce);
    zend_class_implements(logs_exporter_ce, 1, logs_exporter_iface_ce);
    logs_exporter_ce->ce_flags |= ZEND_ACC_FINAL;

    zend_declare_property_null(logs_exporter_ce, "transport", sizeof("transport") - 1, ZEND_ACC_PRIVATE);
}