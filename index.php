<?php

/**
 * @defgroup plugins_generic_dataverse
 */

/**
 * @file plugins/generic/dataverse/index.php
 *
 * Copyright (c) 2019-2021 Lepidus Tecnologia
 * Copyright (c) 2020-2021 SciELO
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @ingroup plugins_generic_dataverse
 * @brief Wrapper for Dataverse plugin.
 *
 */

require_once('DataversePlugin.inc.php');

return new DataversePlugin();
