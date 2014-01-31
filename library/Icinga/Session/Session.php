<?php
// {{{ICINGA_LICENSE_HEADER}}}
/**
 * This file is part of Icinga Web 2.
 *
 * Icinga Web 2 - Head for multiple monitoring backends.
 * Copyright (C) 2014 Icinga Development Team
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * @copyright  2014 Icinga Development Team <info@icinga.org>
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GPL, version 2
 * @author     Icinga Development Team <info@icinga.org>
 *
 */
// {{{ICINGA_LICENSE_HEADER}}}

namespace Icinga\Session;


/**
 * Base class for handling sessions
 */
abstract class Session extends SessionNamespace
{
    /**
     * Container for session namespaces
     *
     * @var array
     */
    protected $namespaces = array();

    /**
     * Read all values from the underlying session implementation
     */
    abstract public function read();

    /**
     * Persists changes to the underlying session implementation
     */
    abstract public function write();

    /**
     * Purge session
     */
    abstract public function purge();

    /**
     * Get or create a new session namespace
     *
     * @param   string      $identifier     The namespace's identifier
     *
     * @return  SessionNamespace
     */
    public function getNamespace($identifier)
    {
        if (!isset($this->namespaces[$identifier])) {
            $this->namespaces[$identifier] = new SessionNamespace();
        }

        return $this->namespaces[$identifier];
    }

    /**
     * Clear all values and namespaces from the session cache
     */
    public function clear()
    {
        $this->values = array();
        $this->namespaces = array();
    }
}